<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminChatService;
use App\Service\FlowStepsService;
use App\Service\MultimodalFlowResolver;
use App\Service\OperatorCatalog;
use App\Service\SeasonPolicyCatalog;
use App\Service\TicketExtraction\ExtractorBroker;
use App\Service\TicketExtraction\HeuristicsExtractor;
use App\Service\TicketExtraction\LlmExtractor;
use App\Service\TicketExtraction\LlmSegmentsExtractor;
use App\Service\TicketParseService;
use Cake\Routing\Router;
use Cake\Utility\Text;
use Psr\Http\Message\UploadedFileInterface;

class PassengerController extends AppController
{
    public function start(): void
    {
        $snapshot = $this->buildSnapshot();
        $transportFlows = $this->buildTransportFlows();
        $quickLinks = [
            'flowStart' => Router::url('/flow/start', true),
            'flowJourney' => Router::url('/flow/journey', true),
            'flowCompensation' => Router::url('/flow/compensation', true),
            'case' => $this->buildPassengerCaseUrl(fullBase: true),
            'review' => Router::url('/passenger/review', true),
            'commuter' => Router::url('/passenger/commuter', true),
            'claims' => Router::url('/passenger/claims', true),
            'faq' => Router::url('/passenger/faq', true),
        ];
        $passengerNav = $this->buildPassengerNav('dashboard');

        $this->set(compact('snapshot', 'quickLinks', 'transportFlows', 'passengerNav'));
    }

    public function case(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $isAdminCaseView = (bool)$session->read('admin.mode') && (string)$this->request->getQuery('admin') === '1';
        $requestedRef = trim((string)($this->request->getQuery('ref') ?? ''));
        [$form, $flags, $meta, $compute, $incident] = $this->loadPassengerCaseFlow($session, $requestedRef);
        $this->backfillAirRerouteUsedOrAccepted($form);
        $session->write('flow.form', $form);
        $meta = $this->refreshPassengerFlowMeta($form, $flags, $meta);
        $session->write('flow.meta', $meta);
        $requestedStep = trim((string)($this->request->getQuery('step') ?? $this->request->getData('goto_step') ?? $this->request->getData('active_case_step') ?? $this->request->getData('case_step') ?? ''));
        $transportMode = strtolower(trim((string)($form['transport_mode'] ?? '')));
        $isFerryCase = $transportMode === 'ferry';
        $isRailCase = $transportMode === 'rail';
        $caseRefKey = $isFerryCase ? 'ferry_case_ref' : ($isRailCase ? 'rail_case_ref' : 'air_case_ref');
        $caseIdKey = $isFerryCase ? 'ferry_case_id' : ($isRailCase ? 'rail_case_id' : 'air_case_id');
        $caseCreatedAtKey = $isFerryCase ? 'ferry_case_created_at' : ($isRailCase ? 'rail_case_created_at' : 'air_case_created_at');

        if ((string)$this->request->getQuery('create') === '1' && empty($meta[$caseCreatedAtKey])) {
            $meta[$caseCreatedAtKey] = date('c');
            $meta = $this->persistPassengerCase($form, $flags, $meta, $compute, $incident);
            $session->write('flow.meta', $meta);
            $this->Flash->success('Case er oprettet i kontrolpanelet.');
        } elseif (!empty($meta[$caseCreatedAtKey]) && empty($meta[$caseIdKey])) {
            $meta = $this->persistPassengerCase($form, $flags, $meta, $compute, $incident);
            $session->write('flow.meta', $meta);
        }
        if (!$this->request->is(['post', 'put', 'patch']) && !empty($meta[$caseRefKey]) && $requestedRef === '') {
            return $this->redirect('/passenger/case?ref=' . rawurlencode((string)$meta[$caseRefKey]) . ($requestedStep !== '' ? '&step=' . rawurlencode($requestedStep) : ''));
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $posted = (array)$this->request->getData();
            $uploadedFiles = [];
            try {
                $uploadedFiles = (array)$this->request->getUploadedFiles();
            } catch (\Throwable) {
                $uploadedFiles = [];
            }
            $allowedKeys = [
                'air_backend_has_expenses',
                'air_backend_needs_documents',
                'incident_main',
                'protected_connection_missed',
                'reroute_offered',
                'remedyChoice',
                'refund_requested',
                'air_article8_choice_offered',
                'air_refund_scope',
                'ferry_refund_scope',
                'ferry_no_real_choice',
                'air_return_to_first_departure_point',
                'air_self_arranged_reroute',
                'air_self_arranged_reroute_reason',
                'air_airline_confirmed_self_arranged_solution',
                'return_to_origin_expense',
                'return_to_origin_amount',
                'return_to_origin_currency',
                'reroute_later_outcome',
                'reroute_later_self_paid_amount',
                'reroute_later_self_paid_currency',
                'voluntary_denied_boarding',
                'extraordinary_circumstances',
                'cancellation_notice_band',
                'reroute_departure_band',
                'reroute_arrival_band',
                'reroute_used_or_accepted',
                'reroute_arrival_delay_minutes',
                'air_reroute_expenses_incurred',
                'air_reroute_expense_type',
                'air_reroute_expense_amount',
                'air_reroute_expense_currency',
                'air_reroute_expense_description',
                'pmr_user',
                'pmr_booked',
                'pmr_delivered_status',
                'pmr_promised_missing',
                'pmr_companion',
                'pmr_service_dog',
                'ferry_pmr_companion',
                'ferry_pmr_service_dog',
                'ferry_pmr_notice_48h',
                'ferry_pmr_met_checkin_time',
                'ferry_pmr_special_needs_notified_at_booking',
                'ferry_pmr_assistance_delivered',
                'ferry_pmr_alternative_transport_offered',
                'unaccompanied_minor',
                'assistance_pmr_priority_applied',
                'assistance_pmr_companion_supported',
                'assistance_pmr_dog_supported',
                'assistance_child_priority_applied',
                'child_delivered_status',
                'firstName',
                'lastName',
                'contact_email',
                'contact_phone',
                'address_street',
                'address_no',
                'address_postalCode',
                'address_city',
                'address_country',
                'payoutPreference',
                'accountHolderName',
                'iban',
                'bic',
                'poa_accepted',
                'fee_terms_accepted',
                'privacy_accepted',
                'signer_name',
                'signer_email',
                'gdprConsent',
                'additionalInfo',
                'meal_offered',
                'meal_self_paid_amount',
                'meal_self_paid_currency',
                'hotel_offered',
                'overnight_needed',
                'hotel_self_paid_amount',
                'hotel_self_paid_currency',
                'hotel_self_paid_nights',
                'assistance_hotel_transport_included',
                'hotel_transport_self_paid_amount',
                'hotel_transport_self_paid_currency',
            ];
            if ($isAdminCaseView) {
                $allowedKeys = array_merge($allowedKeys, [
                    'dep_station',
                    'arr_station',
                    'dep_date',
                    'dep_time',
                    'arr_time',
                    'operator',
                    'train_number',
                    'ticket_no',
                    'booking_reference',
                    'ferry_vessel_name',
                    'operating_carrier',
                    'marketing_carrier',
                    'flight_number',
                    'air_route_type',
                    'air_stopover_airports',
                    'air_connection_type',
                ]);
            }
            foreach ($allowedKeys as $key) {
                if (array_key_exists($key, $posted)) {
                    $form[$key] = is_array($posted[$key]) ? $posted[$key] : trim((string)$posted[$key]);
                }
            }
            if (($form['incident_main'] ?? '') !== 'delay' && in_array((string)($form['remedyChoice'] ?? ''), ['no_refund', 'no_refund_continue'], true)) {
                unset($form['remedyChoice']);
            } elseif (($form['incident_main'] ?? '') === 'delay' && ($form['remedyChoice'] ?? '') === 'no_refund') {
                $form['remedyChoice'] = 'no_refund_continue';
            }
            if ($isFerryCase) {
                if ((string)($form['ferry_refund_scope'] ?? '') !== '') {
                    $form['air_refund_scope'] = (string)$form['ferry_refund_scope'];
                } elseif ((string)($form['air_refund_scope'] ?? '') !== '') {
                    $form['ferry_refund_scope'] = (string)$form['air_refund_scope'];
                }
                $form['ferry_no_real_choice'] = ((string)($form['remedyChoice'] ?? '') === 'no_real_choice') ? 'yes' : 'no';
            }
            if (array_key_exists('incident_main', $form) && trim((string)$form['incident_main']) !== '') {
                $incident['main'] = trim((string)$form['incident_main']);
            }
            if (($form['remedyChoice'] ?? '') === 'refund_return') {
                $form['refund_requested'] = 'yes';
            } elseif (isset($form['remedyChoice'])) {
                $form['refund_requested'] = 'no';
            }
            if (($form['air_backend_has_expenses'] ?? '') !== 'yes') {
                foreach ([
                    'air_reroute_expenses_incurred',
                    'air_reroute_expense_type',
                    'air_reroute_expense_amount',
                    'air_reroute_expense_currency',
                    'air_reroute_expense_description',
                    'meal_self_paid_amount',
                    'meal_self_paid_currency',
                    'hotel_self_paid_amount',
                    'hotel_self_paid_currency',
                    'hotel_self_paid_nights',
                    'hotel_transport_self_paid_amount',
                    'hotel_transport_self_paid_currency',
                ] as $key) {
                    unset($form[$key]);
                }
            }
            $transportMode = strtolower(trim((string)($form['transport_mode'] ?? '')));
            if (($form['pmr_user'] ?? '') !== 'yes') {
                foreach ([
                    'pmr_booked',
                    'pmr_promised_missing',
                    'pmr_companion',
                    'pmr_service_dog',
                    'ferry_pmr_companion',
                    'ferry_pmr_service_dog',
                    'ferry_pmr_notice_48h',
                    'ferry_pmr_met_checkin_time',
                    'ferry_pmr_special_needs_notified_at_booking',
                    'ferry_pmr_assistance_delivered',
                    'ferry_pmr_alternative_transport_offered',
                    'assistance_pmr_companion_supported',
                    'assistance_pmr_dog_supported',
                ] as $key) {
                    unset($form[$key]);
                }
            }
            if ($transportMode === 'air') {
                foreach ([
                    'pmr_booked',
                    'pmr_promised_missing',
                    'assistance_pmr_companion_supported',
                    'assistance_pmr_dog_supported',
                ] as $key) {
                    unset($form[$key]);
                }
                if (($form['pmr_user'] ?? '') !== 'yes') {
                    unset($form['pmr_companion'], $form['pmr_service_dog']);
                }
                if (($form['pmr_user'] ?? '') !== 'yes' && ($form['unaccompanied_minor'] ?? '') !== 'yes') {
                    unset($form['pmr_delivered_status'], $form['assistance_pmr_priority_applied']);
                }
                if (($form['unaccompanied_minor'] ?? '') !== 'yes') {
                    unset($form['assistance_child_priority_applied'], $form['child_delivered_status']);
                }
            }
            if (isset($form['gdprConsent'])) {
                $form['gdprConsent'] = $this->truthy($form['gdprConsent']) ? '1' : '0';
            }
            foreach (['poa_accepted', 'fee_terms_accepted', 'privacy_accepted'] as $consentField) {
                $form[$consentField] = $this->truthy($form[$consentField] ?? '') ? '1' : '0';
            }
            $form['gdprConsent'] = $form['privacy_accepted'] ?? '0';
            if (($form['signer_name'] ?? '') === '') {
                $form['signer_name'] = trim((string)($form['firstName'] ?? '') . ' ' . (string)($form['lastName'] ?? ''));
            }
            if (($form['signer_email'] ?? '') === '') {
                $form['signer_email'] = trim((string)($form['contact_email'] ?? ''));
            }
            $meta['poa'] = [
                'accepted' => ($form['poa_accepted'] ?? '0') === '1',
                'accepted_at' => (($form['poa_accepted'] ?? '0') === '1')
                    ? (string)(($meta['poa']['accepted_at'] ?? '') !== '' ? $meta['poa']['accepted_at'] : date('c'))
                    : '',
                'ip' => (($form['poa_accepted'] ?? '0') === '1')
                    ? (string)(($meta['poa']['ip'] ?? '') !== '' ? $meta['poa']['ip'] : ($this->request->clientIp() ?? ''))
                    : '',
                'user_agent' => (($form['poa_accepted'] ?? '0') === '1')
                    ? (string)(($meta['poa']['user_agent'] ?? '') !== '' ? $meta['poa']['user_agent'] : $this->request->getHeaderLine('User-Agent'))
                    : '',
                'text_version' => 'v1',
                'fee_terms_accepted' => ($form['fee_terms_accepted'] ?? '0') === '1',
                'privacy_accepted' => ($form['privacy_accepted'] ?? '0') === '1',
                'signer_name' => (string)($form['signer_name'] ?? ''),
                'signer_email' => (string)($form['signer_email'] ?? ''),
            ];
            $form['payoutPreference'] = 'bank';
            $meta = $this->storeCaseUploads($meta, $form);
            [$form, $meta] = $this->applyReliableTicketAnalysisToForm($form, $meta);
            $stepKey = trim((string)($posted['active_case_step'] ?? $posted['case_step'] ?? ''));
            $caseTravelState = strtolower(trim((string)($flags['travel_state'] ?? ($form['travel_state'] ?? ($meta['entry_travel_state'] ?? 'completed')))));
            $caseIsOngoing = $caseTravelState === 'ongoing';
            $legacyExpenseItems = $this->normalizeStoredExpenseItems((array)($form['air_case_expense_items'] ?? []));
            [$legacyRefundItems, $legacyCareItems] = $this->splitExpenseItemsByKind($legacyExpenseItems);
            $existingRefundItems = $this->normalizeStoredExpenseItems((array)($form['air_case_refund_expense_items'] ?? []));
            if ($existingRefundItems === []) {
                $existingRefundItems = $this->normalizeFrontendReturnExpenseSeedItems((array)($form['air_return_expense_items'] ?? []));
            }
            if ($existingRefundItems === [] && $caseIsOngoing) {
                $existingRefundItems = $this->normalizeStoredExpenseItems((array)($form['air_reroute_expense_items'] ?? []));
            }
            $existingCareItems = $this->normalizeStoredExpenseItems((array)($form['air_case_care_expense_items'] ?? []));
            if ($existingRefundItems === [] && $legacyRefundItems !== []) {
                $existingRefundItems = $legacyRefundItems;
            }
            if ($existingCareItems === [] && $legacyCareItems !== []) {
                $existingCareItems = $legacyCareItems;
            }
            if ($stepKey === 'refund') {
                [$refundExpenseItems, $refundReceiptFiles] = $this->normalizeCaseExpenseItems(
                    (array)($posted['air_case_refund_expense_items'] ?? []),
                    (array)($uploadedFiles['air_case_refund_receipts'] ?? []),
                    $existingRefundItems
                );
                $form['air_case_refund_expense_items'] = $refundExpenseItems;
                $meta['air_backend_refund_receipt_files'] = $refundReceiptFiles;
            } else {
                $form['air_case_refund_expense_items'] = $existingRefundItems;
                $meta['air_backend_refund_receipt_files'] = (array)($meta['air_backend_refund_receipt_files'] ?? []);
            }
            if ($stepKey === 'support') {
                [$careExpenseItems, $careReceiptFiles] = $this->normalizeCaseExpenseItems(
                    (array)($posted['air_case_care_expense_items'] ?? []),
                    (array)($uploadedFiles['air_case_care_receipts'] ?? []),
                    $existingCareItems
                );
                $form['air_case_care_expense_items'] = $careExpenseItems;
                $meta['air_backend_care_receipt_files'] = $careReceiptFiles;
            } else {
                $form['air_case_care_expense_items'] = $existingCareItems;
                $meta['air_backend_care_receipt_files'] = (array)($meta['air_backend_care_receipt_files'] ?? []);
            }
            unset($form['air_case_expense_items']);
            unset($meta['air_backend_receipt_files']);
            $allExpenseItems = array_merge(
                (array)($form['air_case_refund_expense_items'] ?? []),
                (array)($form['air_case_care_expense_items'] ?? [])
            );
            $this->applyCaseExpenseItemsToLegacyFields($form, $allExpenseItems);
            $meta = $this->persistPassengerCase($form, $flags, $meta, $compute, $incident);
            $session->write('flow.form', $form);
            $session->write('flow.incident', $incident);
            $session->write('flow.meta', $meta);
            $this->Flash->success('Case-backend er opdateret.');

            $redirectStep = trim((string)($posted['goto_step'] ?? $posted['active_case_step'] ?? $requestedStep));
            $redirectUrl = '/passenger/case';
            $redirectParams = [];
            $redirectCaseRef = (string)($meta[$caseRefKey] ?? ($meta['air_case_ref'] ?? $meta['ferry_case_ref'] ?? $meta['rail_case_ref'] ?? ''));
            if ($redirectCaseRef !== '') {
                $redirectParams[] = 'ref=' . rawurlencode($redirectCaseRef);
            }
            if ($redirectStep !== '') {
                $redirectParams[] = 'step=' . rawurlencode($redirectStep);
            }
            if ($isAdminCaseView) {
                $redirectParams[] = 'admin=1';
            }
            if ($redirectParams !== []) {
                $redirectUrl .= '?' . implode('&', $redirectParams);
            }

            return $this->redirect($redirectUrl);
        }

        $snapshot = $this->buildSnapshot();
        $selectedFlight = (array)($meta['air_selected_flight'] ?? []);
        $selectedLeg = (array)($meta['air_selected_leg'] ?? []);
        $caseData = $this->buildAirCompletedCaseData($snapshot, $selectedFlight, $selectedLeg, $meta, $requestedStep);
        $caseData['isAdminCaseView'] = $isAdminCaseView;
        $passengerNav = $this->buildPassengerNav('cases');

        $this->set(compact('snapshot', 'caseData', 'passengerNav'));
        return null;
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: array<string,mixed>, 3: array<string,mixed>, 4: array<string,mixed>}
     */
    private function loadPassengerCaseFlow(\Cake\Http\Session $session, string $requestedRef = ''): array
    {
        $form = (array)$session->read('flow.form') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        $sessionRef = trim((string)($meta['air_case_ref'] ?? ($meta['ferry_case_ref'] ?? ($meta['rail_case_ref'] ?? ''))));

        if (($form !== [] || $flags !== [] || $meta !== [] || $compute !== [] || $incident !== [])
            && ($requestedRef === '' || $requestedRef === $sessionRef)
        ) {
            return [$form, $flags, $meta, $compute, $incident];
        }

        $lookupRef = $requestedRef !== '' ? $requestedRef : $sessionRef;
        if ($lookupRef === '') {
            return [$form, $flags, $meta, $compute, $incident];
        }

        try {
            $cases = $this->fetchTable('Cases');
            $case = $cases->find()->where(['ref' => $lookupRef])->first();
            if ($case === null) {
                return [$form, $flags, $meta, $compute, $incident];
            }
            $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
            if (!is_array($snapshot)) {
                return [$form, $flags, $meta, $compute, $incident];
            }

            $form = (array)($snapshot['form'] ?? []);
            $flags = (array)($snapshot['flags'] ?? []);
            $meta = (array)($snapshot['meta'] ?? []);
            $compute = (array)($snapshot['compute'] ?? []);
            $incident = (array)($snapshot['incident'] ?? []);
            [$form, $meta] = $this->applyReliableTicketAnalysisToForm($form, $meta);
            $meta = $this->refreshPassengerFlowMeta($form, $flags, $meta);
            $transportMode = strtolower(trim((string)($form['transport_mode'] ?? '')));
            if ($transportMode === 'ferry') {
                $meta['ferry_case_id'] = (int)$case->get('id');
                $meta['ferry_case_ref'] = (string)$case->get('ref');
            } elseif ($transportMode === 'rail') {
                $meta['rail_case_id'] = (int)$case->get('id');
                $meta['rail_case_ref'] = (string)$case->get('ref');
            } else {
                $meta['air_case_id'] = (int)$case->get('id');
                $meta['air_case_ref'] = (string)$case->get('ref');
            }

            $session->write('flow.form', $form);
            $session->write('flow.flags', $flags);
            $session->write('flow.meta', $meta);
            $session->write('flow.compute', $compute);
            $session->write('flow.incident', $incident);
        } catch (\Throwable) {
            // Keep page functional even if case hydration fails.
        }

        return [$form, $flags, $meta, $compute, $incident];
    }

    /**
     * Repairs older air sessions where the reroute acceptance answer was saved
     * incompletely even though Article 7(2)-specific facts make it clear.
     *
     * @param array<string,mixed> $form
     */
    private function backfillAirRerouteUsedOrAccepted(array &$form): void
    {
        if (trim((string)($form['reroute_used_or_accepted'] ?? '')) !== '') {
            return;
        }
        if (!in_array((string)($form['remedyChoice'] ?? ''), ['reroute_soonest', 'reroute_later'], true)) {
            return;
        }
        if ((string)($form['reroute_offered'] ?? '') !== 'yes') {
            return;
        }
        if ((string)($form['air_self_arranged_reroute'] ?? '') === 'yes') {
            return;
        }

        $hasAcceptedRerouteEvidence = trim((string)($form['reroute_arrival_delay_minutes'] ?? '')) !== '';

        if ($hasAcceptedRerouteEvidence) {
            $form['reroute_used_or_accepted'] = 'yes';
        }
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $flags
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function refreshPassengerFlowMeta(array $form, array $flags, array $meta): array
    {
        try {
            $resolverFlow = [
                'form' => $form,
                'flags' => $flags,
                'meta' => $meta,
            ];
            $meta['_multimodal'] = (new MultimodalFlowResolver())->evaluate($resolverFlow);
        } catch (\Throwable) {
            // Keep backend functional even if multimodal recompute fails.
        }

        return $meta;
    }

    public function review(): void
    {
        $selectedContext = $this->buildSelectedContext();
        $contextApplied = false;

        if ($selectedContext !== []) {
            (new AdminChatService())->applyContextToFlow($this->request->getSession(), $selectedContext);
            $contextApplied = true;
        }

        $snapshot = $this->buildSnapshot();
        $this->set(compact('snapshot', 'selectedContext', 'contextApplied'));
    }

    public function commuter(): void
    {
        $snapshot = $this->buildSnapshot();
        $coverage = null;

        try {
            $opCat = new OperatorCatalog();
            $seasonCat = new SeasonPolicyCatalog();
            $coverage = $seasonCat->coverageReport($opCat, true);
        } catch (\Throwable) {
            $coverage = null;
        }

        $this->set(compact('snapshot', 'coverage'));
    }

    public function claims(): void
    {
        $snapshot = $this->buildSnapshot();
        $claimLinks = [
            'case' => $this->buildPassengerCaseUrl(fullBase: true),
            'compensation' => Router::url('/flow/compensation', true),
            'applicant' => Router::url('/flow/applicant', true),
            'consent' => Router::url('/flow/consent', true),
            'reimbursement' => Router::url('/reimbursement', true),
            'shadowCases' => Router::url('/api/shadow/cases', true),
        ];
        $passengerNav = $this->buildPassengerNav('cases');

        $this->set(compact('snapshot', 'claimLinks', 'passengerNav'));
    }

    public function trips(): void
    {
        $snapshot = $this->buildSnapshot();
        $apiLinks = [
            'journeys' => Router::url('/api/shadow/journeys', true),
            'chat' => Router::url('/passenger/chat', true),
            'review' => Router::url('/passenger/review', true),
        ];

        $this->set(compact('snapshot', 'apiLinks'));
    }

    public function faq(): void
    {
        $snapshot = $this->buildSnapshot();
        $passengerNav = $this->buildPassengerNav('faq');
        $this->set(compact('snapshot', 'passengerNav'));
    }

    public function chat(): void
    {
        $snapshot = $this->buildSnapshot();
        $csrfToken = (string)($this->request->getAttribute('csrfToken') ?? '');
        $chatUrls = [
            'bootstrap' => Router::url('/api/chat/bootstrap', true),
            'message' => Router::url('/api/chat/message', true),
            'reset' => Router::url('/api/chat/reset', true),
            'context' => Router::url('/api/chat/context', true),
            'upload' => Router::url('/api/chat/upload', true),
        ];
        $initialContext = $this->buildSelectedContext();

        $this->set(compact('snapshot', 'csrfToken', 'chatUrls', 'initialContext'));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSnapshot(): array
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];

        $stepsService = new FlowStepsService();
        $currentAction = $this->inferCurrentAction($flags);
        $steps = $stepsService->buildSteps($flags, $currentAction);
        $visibleSteps = array_values(array_filter($steps, static fn(array $step): bool => (bool)($step['visible'] ?? true)));

        $nextStep = null;
        foreach ($visibleSteps as $step) {
            if (($step['unlocked'] ?? false) && !($step['done'] ?? false)) {
                $nextStep = $step;
                break;
            }
        }

        $ticketMode = (string)($form['ticket_upload_mode'] ?? '');
        $mode = ($ticketMode === 'seasonpass' || ((string)($flags['gate_season_pass'] ?? '')) === '1')
            ? 'commuter'
            : 'standard';

        $route = trim(implode(' → ', array_filter([
            (string)($form['dep_station_name'] ?? $form['from_station'] ?? ''),
            (string)($form['arr_station_name'] ?? $form['to_station'] ?? ''),
        ])));

        return [
            'form' => $form,
            'flags' => $flags,
            'meta' => $meta,
            'mode' => $mode,
            'operator' => (string)($form['operator_name'] ?? $form['operator'] ?? ''),
            'product' => (string)($form['operator_product'] ?? ''),
            'route' => $route,
            'status' => $this->deriveStatus($flags, $mode),
            'steps' => $visibleSteps,
            'nextStep' => $nextStep,
            'completedVisible' => count(array_filter($visibleSteps, static fn(array $step): bool => (bool)($step['done'] ?? false))),
            'visibleTotal' => count($visibleSteps),
        ];
    }

    /**
     * @param array<string,mixed> $flags
     */
    private function inferCurrentAction(array $flags): string
    {
        foreach (FlowStepsService::STEPS as $step) {
            $doneFlag = (string)($step['doneFlag'] ?? '');
            if ($doneFlag === '' || ((string)($flags[$doneFlag] ?? '')) !== '1') {
                return (string)($step['action'] ?? 'start');
            }
        }

        return 'consent';
    }

    /**
     * @param array<string,mixed> $flags
     */
    private function deriveStatus(array $flags, string $mode): string
    {
        if (((string)($flags['step12_done'] ?? '')) === '1') {
            return 'Klar til indsendelse';
        }
        if (((string)($flags['step10_done'] ?? '')) === '1') {
            return 'Resultat klar';
        }
        if (((string)($flags['step5_done'] ?? '')) === '1') {
            return 'Klar til review';
        }
        if (((string)($flags['step2_done'] ?? '')) === '1' && $mode === 'commuter') {
            return 'Pendler-setup aktivt';
        }
        if (((string)($flags['step1_done'] ?? '')) === '1') {
            return 'Sag påbegyndt';
        }

        return 'Ikke startet';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSelectedContext(): array
    {
        $query = $this->request->getQueryParams();
        $context = [];
        foreach ([
            'journey_id',
            'case_file',
            'route_label',
            'status',
            'ticket_mode',
            'operator',
            'operator_country',
            'dep_station',
            'arr_station',
            'incident_main',
            'delay_minutes',
        ] as $key) {
            $value = $query[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === '' || $value === []) {
                continue;
            }
            $context[$key] = $value;
        }

        return $context;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildTransportFlows(): array
    {
        $states = [
            'completed' => ['label' => 'Afsluttet rejse', 'cta' => 'Start afsluttet'],
            'ongoing' => ['label' => 'Igangvaerende rejse', 'cta' => 'Start live'],
        ];
        $modes = [
            'air' => [
                'title' => 'Fly',
                'accent' => '#0f766e',
                'summary' => 'Kort intake nu, billet og dokumentation bagefter. Air bliver eget flow.',
            ],
            'rail' => [
                'title' => 'Tog',
                'accent' => '#1d4ed8',
                'summary' => 'Nuvaerende togflow bevares, men klargores til senere multimodal wrapper.',
            ],
            'bus' => [
                'title' => 'Bus',
                'accent' => '#b45309',
                'summary' => 'Busflow med egne PMR-, assistance- og compensation-spor.',
            ],
            'ferry' => [
                'title' => 'Faerge',
                'accent' => '#0369a1',
                'summary' => 'Faergeflow med egne Art. 16-19 spor og senere multimodal samling.',
            ],
        ];

        $cards = [];
        foreach ($modes as $mode => $config) {
            $links = [];
            foreach ($states as $state => $stateConfig) {
                $links[] = [
                    'state' => $state,
                    'label' => $stateConfig['label'],
                    'cta' => $stateConfig['cta'],
                    'href' => Router::url('/flow/' . $mode . '/' . $state, true),
                ];
            }
            $cards[] = [
                'mode' => $mode,
                'title' => $config['title'],
                'accent' => $config['accent'],
                'summary' => $config['summary'],
                'links' => $links,
            ];
        }

        return $cards;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function buildPassengerNav(string $current): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => 'Kontrolpanel', 'href' => Router::url('/passenger/start', true)],
            ['key' => 'cases', 'label' => 'Sager', 'href' => $this->buildPassengerCaseUrl(fullBase: true)],
            ['key' => 'faq', 'label' => 'FAQ', 'href' => Router::url('/passenger/faq', true)],
        ];

        return array_map(static function (array $item) use ($current): array {
            $item['active'] = $item['key'] === $current ? '1' : '';
            return $item;
        }, $items);
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $selectedFlight
     * @param array<string,mixed> $selectedLeg
     * @param array<string,mixed> $meta
     * @param string $requestedStep
     * @return array<string,mixed>
     */
    private function buildAirCompletedCaseData(array $snapshot, array $selectedFlight, array $selectedLeg, array $meta, string $requestedStep = ''): array
    {
        $form = (array)($snapshot['form'] ?? []);
        $flags = (array)($snapshot['flags'] ?? []);
        $multimodal = (array)($meta['_multimodal'] ?? []);
        $travelState = strtolower(trim((string)($flags['travel_state'] ?? ($form['travel_state'] ?? ($meta['entry_travel_state'] ?? 'completed')))));
        if (!in_array($travelState, ['completed', 'ongoing', 'before_start'], true)) {
            $travelState = 'completed';
        }
        $isOngoing = $travelState === 'ongoing';
        $isCompleted = !$isOngoing;
        $isAirShortCase = strtolower(trim((string)($flags['entry_variant'] ?? ''))) === 'air_short';
        $transportMode = strtolower(trim((string)($form['transport_mode'] ?? 'air')));
        $isFerryCase = $transportMode === 'ferry';
        $isRailCase = $transportMode === 'rail';
        $caseIdKey = $isFerryCase ? 'ferry_case_id' : ($isRailCase ? 'rail_case_id' : 'air_case_id');
        $caseRefKey = $isFerryCase ? 'ferry_case_ref' : ($isRailCase ? 'rail_case_ref' : 'air_case_ref');
        $caseCreatedAtKey = $isFerryCase ? 'ferry_case_created_at' : ($isRailCase ? 'rail_case_created_at' : 'air_case_created_at');
        $airRights = (array)($multimodal['air_rights'] ?? []);
        $ferryRights = (array)($multimodal['ferry_rights'] ?? []);
        $ferryPmrRights = (array)($multimodal['ferry_pmr_rights'] ?? []);
        $railSelectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
        $railOperationalEvidence = (array)($meta['rail_operational_evidence'] ?? []);
        $railIncidentSeed = (array)($meta['rail_incident_seed'] ?? []);
        $selectedDeparture = (array)($meta['ferry_selected_departure'] ?? []);
        $ferryOperationalEvidence = (array)($meta['ferry_operational_evidence'] ?? []);
        $routeLabel = trim(implode(' -> ', array_filter([
            (string)($form['dep_station'] ?? ''),
            (string)($form['arr_station'] ?? ''),
        ])));
        $firstDepartureLabel = trim((string)($form['dep_station'] ?? ($selectedLeg['dep_label'] ?? '')));
        $rerouteOriginLabel = trim((string)($selectedLeg['dep_label'] ?? ($form['dep_station'] ?? '')));
        $flightLabel = trim(implode(' | ', array_filter([
            (string)($selectedFlight['flight_number'] ?? ''),
            (string)($selectedFlight['carrier_name'] ?? ''),
            (string)($selectedLeg['title'] ?? ''),
        ])));
        if ($isFerryCase) {
            $routeLabel = trim(implode(' -> ', array_filter([
                (string)($selectedDeparture['departure_port_name'] ?? ($form['dep_station'] ?? '')),
                (string)($selectedDeparture['arrival_port_name'] ?? ($form['arr_station'] ?? '')),
            ])));
            $ferryDepartureTime = '';
            if (!empty($selectedDeparture['scheduled_departure_local']) && strpos((string)$selectedDeparture['scheduled_departure_local'], 'T') !== false) {
                [, $ferryDepartureTime] = explode('T', (string)$selectedDeparture['scheduled_departure_local'], 2);
                $ferryDepartureTime = substr($ferryDepartureTime, 0, 5);
            }
            $flightLabel = trim(implode(' | ', array_filter([
                (string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? '')),
                (string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? '')),
                $ferryDepartureTime !== '' ? ('Afgang ' . $ferryDepartureTime) : null,
            ])));
        } elseif ($isRailCase) {
            $routeLabel = trim(implode(' -> ', array_filter([
                (string)($railSelectedDeparture['origin_station_name'] ?? ($form['dep_station'] ?? '')),
                (string)($railSelectedDeparture['destination_station_name'] ?? ($form['arr_station'] ?? '')),
            ])));
            $railDepartureTime = '';
            if (!empty($railSelectedDeparture['planned_departure_at']) && preg_match('/\b(\d{2}:\d{2})\b/', (string)$railSelectedDeparture['planned_departure_at'], $m)) {
                $railDepartureTime = $m[1];
            }
            $flightLabel = trim(implode(' | ', array_filter([
                (string)($railSelectedDeparture['train_number'] ?? ($form['train_number'] ?? '')),
                (string)($railSelectedDeparture['operator_name'] ?? ($form['operator'] ?? '')),
                $railDepartureTime !== '' ? ('Afgang ' . $railDepartureTime) : null,
            ])));
        }
        $currencyOptions = ['EUR', 'DKK', 'SEK', 'NOK', 'GBP', 'CHF', 'BGN', 'CZK', 'HUF', 'PLN', 'RON'];
        $incidentExpenseFlag = trim((string)($form['air_incident_expenses_incurred'] ?? ''));
        $backendExpenseFlag = trim((string)($form['air_backend_has_expenses'] ?? ''));
        $incidentMain = trim((string)($form['incident_main'] ?? ''));
        $rerouteOffered = trim((string)($form['reroute_offered'] ?? ''));
        if ($backendExpenseFlag === '') {
            $backendExpenseFlag = $incidentExpenseFlag;
        }
        $hasExpenses = $backendExpenseFlag === 'yes';
        $ticketFiles = (array)($meta['air_backend_ticket_files'] ?? []);
        $legacyExpenseItems = $this->normalizeStoredExpenseItems((array)($form['air_case_expense_items'] ?? []));
        [$legacyRefundItems, $legacyCareItems] = $this->splitExpenseItemsByKind($legacyExpenseItems);
        $refundExpenseItems = $this->normalizeStoredExpenseItems((array)($form['air_case_refund_expense_items'] ?? []));
        if ($refundExpenseItems === []) {
            $refundExpenseItems = $this->normalizeFrontendReturnExpenseSeedItems((array)($form['air_return_expense_items'] ?? []));
        }
        if ($refundExpenseItems === [] && $isOngoing) {
            $refundExpenseItems = $this->normalizeStoredExpenseItems((array)($form['air_reroute_expense_items'] ?? []));
        }
        $careExpenseItems = $this->normalizeStoredExpenseItems((array)($form['air_case_care_expense_items'] ?? []));
        if ($refundExpenseItems === [] && $legacyRefundItems !== []) {
            $refundExpenseItems = $legacyRefundItems;
        }
        if ($careExpenseItems === [] && $legacyCareItems !== []) {
            $careExpenseItems = $legacyCareItems;
        }
        $refundReceiptFiles = (array)($meta['air_backend_refund_receipt_files'] ?? []);
        if ($refundReceiptFiles === []) {
            $refundReceiptFiles = array_values(array_filter(array_map(static function (array $item): ?array {
                $receipt = (array)($item['receipt'] ?? []);
                return isset($receipt['path'], $receipt['name']) ? $receipt : null;
            }, $refundExpenseItems)));
        }
        $careReceiptFiles = (array)($meta['air_backend_care_receipt_files'] ?? []);
        if ($careReceiptFiles === []) {
            $careReceiptFiles = array_values(array_filter(array_map(static function (array $item): ?array {
                $receipt = (array)($item['receipt'] ?? []);
                return isset($receipt['path'], $receipt['name']) ? $receipt : null;
            }, $careExpenseItems)));
        }
        $remedyChoice = trim((string)($form['remedyChoice'] ?? ''));
        if ($incidentMain === 'delay' && $remedyChoice === 'no_refund') {
            $remedyChoice = 'no_refund_continue';
        }
        if ($incidentMain !== 'delay' && in_array($remedyChoice, ['no_refund', 'no_refund_continue'], true)) {
            $remedyChoice = '';
        }
        $refundChosen = $remedyChoice === 'refund_return';
        $refundExpenseKinds = array_values(array_filter(array_map(static fn(array $item): string => trim((string)($item['type'] ?? '')), $refundExpenseItems)));
        $careExpenseKinds = array_values(array_filter(array_map(static fn(array $item): string => trim((string)($item['type'] ?? '')), $careExpenseItems)));
        $hasRerouteData = trim((string)($form['air_article8_choice_offered'] ?? '')) !== ''
            || trim((string)($form['air_self_arranged_reroute'] ?? '')) !== ''
            || trim((string)($form['air_airline_confirmed_self_arranged_solution'] ?? '')) !== ''
            || trim((string)($form['reroute_later_outcome'] ?? '')) !== ''
            || trim((string)($form['air_reroute_expense_amount'] ?? '')) !== ''
            || trim((string)($form['return_to_origin_amount'] ?? '')) !== ''
            || $refundExpenseKinds !== [];
        $hasCareData = trim((string)($form['meal_self_paid_amount'] ?? '')) !== ''
            || trim((string)($form['hotel_self_paid_amount'] ?? '')) !== ''
            || trim((string)($form['hotel_transport_self_paid_amount'] ?? '')) !== ''
            || $careExpenseKinds !== [];
        $hasPmrData = trim((string)($form['pmr_user'] ?? '')) !== ''
            || trim((string)($form['pmr_booked'] ?? '')) !== ''
            || trim((string)($form['pmr_delivered_status'] ?? '')) !== ''
            || trim((string)($form['pmr_promised_missing'] ?? '')) !== ''
            || trim((string)($form['assistance_pmr_priority_applied'] ?? '')) !== '';
        $hasBackendSupportData = $hasCareData
            || $careReceiptFiles !== []
            || trim((string)($form['pmr_delivered_status'] ?? '')) !== ''
            || trim((string)($form['assistance_pmr_priority_applied'] ?? '')) !== ''
            || trim((string)($form['pmr_companion'] ?? '')) !== ''
            || trim((string)($form['pmr_service_dog'] ?? '')) !== ''
            || trim((string)($form['assistance_child_priority_applied'] ?? '')) !== ''
            || trim((string)($form['child_delivered_status'] ?? '')) !== '';
        $hasApplicant = trim((string)($form['contact_email'] ?? '')) !== ''
            || trim((string)($form['firstName'] ?? '')) !== '';
        $hasConsent = trim((string)($form['poa_accepted'] ?? '')) === '1'
            && trim((string)($form['fee_terms_accepted'] ?? '')) === '1'
            && trim((string)($form['privacy_accepted'] ?? ($form['gdprConsent'] ?? ''))) === '1'
            && trim((string)($form['signer_name'] ?? '')) !== ''
            && trim((string)($form['signer_email'] ?? '')) !== '';
        $hasDetails = trim((string)($form['incident_main'] ?? '')) !== ''
            || trim((string)($form['voluntary_denied_boarding'] ?? '')) !== ''
            || trim((string)($form['operatorExceptionalCircumstances'] ?? ($form['extraordinary_circumstances'] ?? ''))) !== ''
            || trim((string)($form['cancellation_notice_band'] ?? '')) !== ''
            || trim((string)($form['protected_connection_missed'] ?? '')) !== '';
        $hasDocuments = $ticketFiles !== [];
        $needsDocs = !$hasDocuments;
        $documentsStatus = $hasDocuments ? 'done' : 'active_needed';
        $isDelayRefundContext = $incidentMain === 'delay';
        $isCancellationRefundContext = $incidentMain === 'cancellation';
        $isDeniedBoardingContext = $incidentMain === 'denied_boarding';
        $isRerouteRemedyForCase = in_array($remedyChoice, ['reroute_soonest', 'reroute_later'], true)
            || ($isFerryCase && $remedyChoice === 'no_real_choice');
        $isDeniedBoardingRerouteRemedy = $isDeniedBoardingContext
            && $isRerouteRemedyForCase;
        $deniedBoardingSkipRefundStep = $isDeniedBoardingRerouteRemedy
            && trim((string)($form['air_self_arranged_reroute'] ?? '')) === 'no';
        $isVoluntaryDeniedBoarding = (string)($form['voluntary_denied_boarding'] ?? '') === 'yes';
        $showDeniedBoardingSupport = $isDeniedBoardingContext && !$isVoluntaryDeniedBoarding;
        $showCancellationCompensationDetails = $isCancellationRefundContext
            && $isRerouteRemedyForCase;
        $showBackendArticle8OfferField = !$isFerryCase && !$isRailCase && $incidentMain === 'missed_connection';
        $airDelayThresholdHours = (int)($form['air_delay_threshold_hours'] ?? ($multimodal['air_scope']['air_delay_threshold_hours'] ?? 0));
        $delayDepartureBand = trim((string)($form['delay_departure_band'] ?? ''));
        $airExpectedDelayBucket = trim((string)($form['air_expected_delay_bucket'] ?? ''));
        $airActualArrivalBucket = trim((string)($form['air_actual_arrival_delay_bucket'] ?? ''));
        $delayCareTriggeredByIncident = $isDelayRefundContext && in_array($airExpectedDelayBucket, ['threshold_to_under_5h', 'five_plus', 'next_day'], true);
        if (!$delayCareTriggeredByIncident) {
            $delayCareTriggeredByIncident = $isDelayRefundContext && in_array($delayDepartureBand, ['threshold_to_under_5h', 'five_plus'], true);
        }
        $delayRefundTriggeredByIncident = $isDelayRefundContext && in_array($airExpectedDelayBucket, ['five_plus', 'next_day'], true);
        if (!$delayRefundTriggeredByIncident) {
            $delayRefundTriggeredByIncident = $isDelayRefundContext && $delayDepartureBand === 'five_plus';
        }
        $gateAirRefund = !empty($airRights['gate_air_reroute_refund'])
            || !empty($airRights['gate_air_delay_refund_5h'])
            || (string)($flags['gate_air_reroute_refund'] ?? '') === '1'
            || (string)($flags['gate_air_delay_refund_5h'] ?? '') === '1'
            || $delayRefundTriggeredByIncident;
        $gateAirCare = !empty($airRights['gate_air_care'])
            || (string)($flags['gate_air_care'] ?? '') === '1'
            || $delayCareTriggeredByIncident;
        $showRefundStep = ($isCompleted && ($gateAirRefund || $isDeniedBoardingContext))
            || ($isOngoing && $isAirShortCase && (($gateAirRefund || $hasRerouteData) || $isDeniedBoardingContext));
        if ($deniedBoardingSkipRefundStep) {
            $showRefundStep = false;
        }
        if ($isDeniedBoardingContext) {
            $showSupportStep = ($isCompleted && $showDeniedBoardingSupport)
                || ($isOngoing && $isAirShortCase && $showDeniedBoardingSupport);
        } else {
            $showSupportStep = ($isCompleted && ($gateAirCare || $hasCareData || trim((string)($form['pmr_user'] ?? '')) === 'yes'))
                || ($isOngoing && $isAirShortCase && ($gateAirCare || $hasCareData || trim((string)($form['pmr_user'] ?? '')) === 'yes'));
        }
        if ($isFerryCase) {
            $gateFerryRemedy = !empty($ferryRights['gate_art18'])
                || !empty($ferryPmrRights['gate_ferry_pmr_remedy_art8_3'])
                || !empty($ferryPmrRights['gate_ferry_pmr_boarding_remedy'])
                || (string)($flags['gate_art18'] ?? '') === '1'
                || (string)($flags['gate_ferry_pmr_remedy'] ?? '') === '1';
            $gateFerryAssistance = !empty($ferryRights['gate_art17_refreshments'])
                || !empty($ferryRights['gate_art17_hotel'])
                || (string)($flags['gate_ferry_art17_refreshments'] ?? '') === '1'
                || (string)($flags['gate_ferry_art17_hotel'] ?? '') === '1';
            $showRefundStep = $gateFerryRemedy || $hasRerouteData || $remedyChoice !== '';
            $showSupportStep = $gateFerryAssistance || $hasCareData || trim((string)($form['pmr_user'] ?? '')) === 'yes';
        } elseif ($isRailCase) {
            $gateRailRemedy = !empty($railIncidentSeed['gate_art18'])
                || (string)($flags['gate_art18'] ?? '') === '1';
            $gateRailAssistance = !empty($railIncidentSeed['gate_art20'])
                || (string)($flags['gate_art20'] ?? '') === '1';
            $showRefundStep = $gateRailRemedy || $hasRerouteData || $remedyChoice !== '';
            $showSupportStep = $gateRailAssistance || $hasCareData || trim((string)($form['pmr_user'] ?? '')) === 'yes';
        }
        $delayBandOptions = match ($airDelayThresholdHours) {
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
                'threshold_to_under_5h' => 'Threshold naaet, men under 5 timer',
                'five_plus' => '5+ timer',
            ],
        };
        $expectedDelayOptions = [
            'under_threshold' => ($airDelayThresholdHours > 0 ? ('Under ' . $airDelayThresholdHours . ' timer') : 'Under threshold'),
            'threshold_to_under_5h' => ($airDelayThresholdHours > 0 ? ('Mindst ' . $airDelayThresholdHours . ' timer') : 'Threshold naaet'),
            'five_plus' => '5+ timer',
            'next_day' => 'Ny afgang foerst naeste dag',
            'unknown' => 'Ved ikke',
        ];
        $actualArrivalOptions = [
            'under_3h' => 'Under 3 timer',
            'three_to_four' => '3-3 timer 59 min',
            'four_plus' => '4+ timer',
            'never_arrived' => 'Ankom aldrig',
            'unknown' => 'Ved ikke',
        ];
        $arrivalDelayMinutes = trim((string)($form['arrival_delay_minutes'] ?? ''));
        $expectedDelayLabel = $expectedDelayOptions[$airExpectedDelayBucket] ?? ($delayBandOptions[$delayDepartureBand] ?? '');
        $actualArrivalDelayLabel = $actualArrivalOptions[$airActualArrivalBucket] ?? '';
        if ($actualArrivalDelayLabel === '' && $arrivalDelayMinutes !== '') {
            $arrivalDelayInt = is_numeric($arrivalDelayMinutes) ? (int)$arrivalDelayMinutes : null;
            if ($arrivalDelayInt !== null) {
                if ($arrivalDelayInt >= 999) {
                    $actualArrivalDelayLabel = 'Ankom aldrig';
                } elseif ($arrivalDelayInt >= 240) {
                    $actualArrivalDelayLabel = '4+ timer';
                } elseif ($arrivalDelayInt >= 180) {
                    $actualArrivalDelayLabel = '3-3 timer 59 min';
                } else {
                    $actualArrivalDelayLabel = 'Under 3 timer';
                }
            }
        }
        $returnToOriginFlag = trim((string)($form['return_to_origin_expense'] ?? ''));
        $overnightNeeded = trim((string)($form['overnight_needed'] ?? ($form['air_next_day_departure'] ?? '')));
        $rerouteUsedOrAccepted = trim((string)($form['reroute_used_or_accepted'] ?? ''));
        $rerouteExpenseFlag = trim((string)($form['air_reroute_expenses_incurred'] ?? ''));
        $noticeBand = (string)($form['cancellation_notice_band'] ?? '');
        $article5WindowFactsRequired = $incidentMain === 'cancellation'
            && $isRerouteRemedyForCase
            && $rerouteOffered === 'yes'
            && in_array($noticeBand, ['7_to_13_days', 'under_7_days', 'airport_on_day_of_departure'], true);
        $article5WindowFactsDone = !$article5WindowFactsRequired
            || (
                trim((string)($form['reroute_departure_band'] ?? '')) !== ''
                && trim((string)($form['reroute_arrival_band'] ?? '')) !== ''
            );
        $hasRefundExpenseAmounts = false;
        $hasReturnExpenseAmounts = !$isCompleted && trim((string)($form['return_to_origin_amount'] ?? '')) !== '';
        $hasRerouteExpenseAmounts = !$isCompleted && trim((string)($form['air_reroute_expense_amount'] ?? '')) !== '';
        foreach ($refundExpenseItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemType = trim((string)($item['type'] ?? ''));
            $itemAmount = trim((string)($item['amount'] ?? ''));
            $itemCurrency = trim((string)($item['currency'] ?? ''));
            $itemIsConcreteExpense = $itemType !== '' && $itemAmount !== '' && $itemCurrency !== '';
            if ($itemIsConcreteExpense) {
                $hasRefundExpenseAmounts = true;
            }
            if ($itemType === 'return_to_origin' && $itemIsConcreteExpense) {
                $hasReturnExpenseAmounts = true;
            }
            if (in_array($itemType, ['new_ticket', 'airport_transfer', 'other_transport', 'expensive_solution', 'other'], true) && $itemIsConcreteExpense) {
                $hasRerouteExpenseAmounts = true;
            }
        }
        $article7EligibilityStatus = trim((string)($airRights['article7_eligibility_status'] ?? ''));
        $article7ReductionStatus = trim((string)($airRights['article7_reduction_status'] ?? ''));
        $article7BaseAmountEur = (int)($airRights['article7_base_amount_eur'] ?? 0);
        $article7FinalAmountEur = (int)($airRights['article7_final_amount_eur'] ?? 0);
        $deniedBoardingSelfArrangedDirectExpensePath = $isDeniedBoardingContext
            && $isRerouteRemedyForCase
            && trim((string)($form['air_self_arranged_reroute'] ?? '')) === 'yes';
        $deniedBoardingSelfArrangedFacts = $isDeniedBoardingContext
            && $isRerouteRemedyForCase
            && (
                $rerouteOffered === 'no'
                || ($rerouteOffered === 'yes' && $rerouteUsedOrAccepted === 'no')
            );
        $showDeniedBoardingCompensationDetails = $isDeniedBoardingContext
            && !$isVoluntaryDeniedBoarding
            && $isRerouteRemedyForCase
            && !$deniedBoardingSelfArrangedDirectExpensePath;
        $deniedBoardingReductionRelevant = $isDeniedBoardingContext
            && !$isVoluntaryDeniedBoarding
            && $article7EligibilityStatus === 'eligible'
            && !$deniedBoardingSelfArrangedDirectExpensePath;
        $cancellationNeedsArticle7ReductionFacts = $incidentMain === 'cancellation'
            && $isRerouteRemedyForCase
            && $rerouteOffered === 'yes'
            && $article7EligibilityStatus === 'eligible';
        $cancellationArticle7ReductionFactsDone = !$cancellationNeedsArticle7ReductionFacts
            || $rerouteUsedOrAccepted !== '';
        $cancellationNeedsPassengerRerouteFacts = $incidentMain === 'cancellation'
            && $isRerouteRemedyForCase
            && (
                $rerouteOffered === 'no'
                || ($rerouteOffered === 'yes' && $rerouteUsedOrAccepted === 'no')
            );
        $returnExpenseFactsDone = $returnToOriginFlag !== 'yes' || $hasReturnExpenseAmounts;
        $rerouteExpenseFactsDone = $rerouteExpenseFlag !== 'yes' || $hasRerouteExpenseAmounts || $hasRefundExpenseAmounts;
        $refundStepDone = false;
        if ($isDelayRefundContext && in_array($remedyChoice, ['no_refund', 'no_refund_continue'], true)) {
            $refundStepDone = true;
        } elseif ($remedyChoice === 'refund_return') {
            $refundStepDone = trim((string)($form['air_refund_scope'] ?? '')) !== ''
                && $returnToOriginFlag !== ''
                && $returnExpenseFactsDone;
        } elseif ($isRerouteRemedyForCase) {
            if ($incidentMain === 'cancellation') {
                $refundStepDone = $rerouteOffered !== ''
                    && $article5WindowFactsDone
                    && $cancellationArticle7ReductionFactsDone
                    && (
                        !$cancellationNeedsPassengerRerouteFacts
                        || trim((string)($form['air_self_arranged_reroute'] ?? '')) !== ''
                    )
                    && $rerouteExpenseFactsDone;
            } else {
                if ($isDeniedBoardingContext) {
                    if ($deniedBoardingSelfArrangedDirectExpensePath) {
                        $refundStepDone = $rerouteExpenseFactsDone;
                    } else {
                        $refundStepDone = $rerouteOffered !== ''
                            && (
                                $rerouteOffered !== 'yes'
                                || $rerouteUsedOrAccepted !== ''
                            )
                            && (
                                !$deniedBoardingSelfArrangedFacts
                                || trim((string)($form['air_self_arranged_reroute'] ?? '')) !== ''
                            )
                            && $rerouteExpenseFactsDone;
                    }
                } else {
                    $refundStepDone = trim((string)($form['air_self_arranged_reroute'] ?? '')) !== ''
                        && $rerouteExpenseFactsDone;
                }
            }
        }
        $mealAnswered = trim((string)($form['meal_offered'] ?? '')) !== '';
        $hotelAnswered = $overnightNeeded !== ''
            && ($overnightNeeded !== 'yes' || (
                trim((string)($form['hotel_offered'] ?? '')) !== ''
                && trim((string)($form['assistance_hotel_transport_included'] ?? '')) !== ''
            ));
        $supportStatus = $hasBackendSupportData ? 'done' : 'todo';
        $supportSummary = $isFerryCase
            ? 'Ferry Art. 17 assistance registreres her med tydelig opdeling mellem forplejning, hotel, hoteltransport og PMR-evidence.'
            : ($isRailCase
                ? 'Rail Art. 20 assistance registreres her med tydelig opdeling mellem maaltider, hotel, strandingskontekst og dokumenterede egne udgifter.'
                : ($isOngoing
                    ? 'Detaljerede care-belob, hoteludgifter og kvitteringer kan efterregistreres her efter den korte liveform.'
                    : 'Article 9-care registreres her med tydelig opdeling mellem maaltider, overnatning, hoteltransport og PMR.'));
        $compFallbackReasons = [];
        if ($refundChosen) {
            $compFallbackReasons[] = $isFerryCase
                ? 'Ferry Art. 18-refusion er valgt. Art. 19-kompensation vurderes separat, mens fokus her er billetrefusion og relevante retur-/videre-rejseudgifter.'
                : ($isRailCase
                    ? 'Rail Art. 18-refusion er valgt. Art. 19-kompensation vurderes separat, mens fokus her er billetrefusion og relevante retur-/videre-rejseudgifter.'
                    : 'Refusion er valgt. I den nuvaerende app gates Art. 7-kompensation ud, og fokus flyttes til billetrefusion og returudgifter.');
        }
        if ((string)($form['voluntary_denied_boarding'] ?? '') === 'yes') {
            $compFallbackReasons[] = 'Boardingafvisning var frivillig. Flat-kompensation bortfalder normalt i den situation.';
        }
        $operatorExceptional = (string)($form['operatorExceptionalCircumstances'] ?? ($form['extraordinary_circumstances'] ?? ''));
        $operatorExceptionalType = (string)($form['operatorExceptionalType'] ?? '');
        $ticketAnalysis = (array)($meta['air_backend_ticket_analysis'] ?? []);
        $extraordinaryTypeLabels = [
            'weather' => 'Vejr',
            'air_traffic_control' => 'ATC / luftrum',
            'security' => 'Sikkerhed',
            'external_strike' => 'Ekstern strejke',
            'own_staff_strike' => 'Egen personalestrejke',
            'technical_issue' => 'Teknisk fejl',
            'crew_shortage' => 'Crew / bemanding',
            'other' => 'Andet',
        ];
        if ($operatorExceptional === 'yes') {
            $compFallbackReasons[] = $isFerryCase
                ? 'Transportoeren paaberaaber force majeure/saerlige forhold. Det kan paavirke Art. 19 og dele af assistance, men ikke automatisk Art. 18-refusion.'
                : 'Flyselskabet paaberaaber extraordinary circumstances. Det kan slaa kompensationen ud, men ikke care eller refusion.';
            if ($operatorExceptionalType !== '') {
                $compFallbackReasons[] = 'Den oplyste begrundelse er registreret som: '
                    . ($extraordinaryTypeLabels[$operatorExceptionalType] ?? $operatorExceptionalType)
                    . '.';
            }
        }
        if ($noticeBand === '14_plus_days') {
            $compFallbackReasons[] = 'Aflysning varslet mindst 14 dage foer. Flat-kompensation falder normalt bort.';
        }
        if ($incidentMain === 'delay' && $isCompleted && !$showRefundStep) {
            $compFallbackReasons[] = $isFerryCase
                ? 'Delay-sporet har ikke aabnet Art. 18-refusion endnu. For ferry kraever Art. 18 normalt aflysning eller afgangsforsinkelse over 90 minutter.'
                : ($isRailCase
                    ? 'Delay-sporet har ikke aabnet rail Art. 18 endnu. For rail kraever Art. 18 normalt aflysning, mistet forbindelse eller forventet/faktisk ankomstforsinkelse paa mindst 60 minutter.'
                    : 'Delay-sporet har ikke aabnet Article 8-refusion endnu. Ved flyforsinkelse kraever refund som udgangspunkt 5+ timer.');
        }
        if ($incidentMain === 'delay' && $isCompleted && !$showSupportStep) {
            $compFallbackReasons[] = $isRailCase
                ? 'Delay-sporet har ikke aabnet rail Art. 20 endnu ud fra de nuvaerende thresholds og svar.'
                : 'Delay-sporet har ikke aabnet et separat care-step i backend ud fra de nuvaerende thresholds og svar.';
        }
        if ($isOngoing) {
            $compFallbackReasons[] = $isFerryCase
                ? 'Dette er en igangvaerende ferry-sag. Liveflowet holder sig til afgang, ETA/ATA og gates, mens backend samler beloeb, kvitteringer og supplerende oplysninger bagefter.'
                : ($isRailCase
                    ? 'Dette er en igangvaerende rail-sag. Liveflowet holder sig til afgang, forsinkelses-seed og gates, mens backend samler beloeb, kvitteringer og supplerende oplysninger bagefter.'
                    : 'Dette er en igangvaerende flysag. Liveflowet holder sig til type- og gate-spoergsmaal, mens backend kan samle de tunge beloeb, kvitteringer og supplerende oplysninger bagefter.');
        }
        if ($deniedBoardingSkipRefundStep) {
            $compFallbackReasons[] = 'Passageren har ikke selv maattet finde videre rejse i remedies. Derfor springes Afhjaelpning over i backend, og sagen gaar direkte videre til Assistance.';
        }
        $steps = [
            [
                'key' => 'refund',
                'label' => 'Afhjaelpning',
                'status' => $refundStepDone ? 'done' : 'todo',
            ],
            [
                'key' => 'support',
                'label' => 'Assistance',
                'status' => $supportStatus,
            ],
            [
                'key' => 'documents',
                'label' => 'Dokumenter',
                'status' => $documentsStatus,
            ],
            [
                'key' => 'applicant',
                'label' => 'Ansøger og udbetaling',
                'status' => $hasApplicant ? 'done' : 'todo',
            ],
            [
                'key' => 'consent',
                'label' => 'Fuldmagt og samtykke',
                'status' => $hasConsent ? 'done' : 'todo',
            ],
        ];
        $steps = array_values(array_filter($steps, static function (array $step) use ($showRefundStep, $showSupportStep): bool {
            $key = (string)($step['key'] ?? '');
            if ($key === 'refund') {
                return $showRefundStep;
            }
            if ($key === 'support') {
                return $showSupportStep;
            }

            return true;
        }));

        $allowedSteps = array_column($steps, 'key');
        $activeStep = in_array($requestedStep, $allowedSteps, true) ? $requestedStep : '';
        if ($activeStep === '') {
            foreach ($steps as $step) {
                if ((string)$step['status'] !== 'done') {
                    $activeStep = (string)$step['key'];
                    break;
                }
            }
        }
        if ($activeStep === '') {
            $activeStep = (string)($steps[0]['key'] ?? 'documents');
        }

        $steps = array_map(static function (array $step) use ($activeStep): array {
            $step['active'] = $step['key'] === $activeStep ? '1' : '';
            return $step;
        }, $steps);

        $progressCompleted = count(array_filter($steps, static function (array $step): bool {
            return (string)($step['status'] ?? '') === 'done';
        }));
        $progressTotal = count(array_filter($steps, static function (array $step): bool {
            return (string)($step['status'] ?? '') !== 'optional';
        }));
        $progressPct = $progressTotal > 0 ? (int)round(($progressCompleted / $progressTotal) * 100) : 0;

        return [
            'caseId' => (int)($meta[$caseIdKey] ?? 0),
            'caseRef' => (string)($meta[$caseRefKey] ?? ''),
            'travelState' => $travelState,
            'status' => (string)($snapshot['status'] ?? 'Sag paabegyndt'),
            'routeLabel' => $routeLabel !== '' ? $routeLabel : ($isFerryCase ? 'Ferry-sag uden fuld rute endnu' : ($isRailCase ? 'Rail-sag uden fuld rute endnu' : 'Air-sag uden fuld rute endnu')),
            'flightLabel' => $flightLabel,
            'date' => (string)($form['dep_date'] ?? ''),
            'incident' => (string)($form['incident_main'] ?? ''),
            'incidentMain' => $incidentMain,
            'isDeniedBoardingContext' => $isDeniedBoardingContext,
            'rerouteOffered' => $rerouteOffered,
            'firstDepartureLabel' => $firstDepartureLabel,
            'rerouteOriginLabel' => $rerouteOriginLabel,
            'overnightNeeded' => $overnightNeeded,
            'expectedDelayLabel' => $expectedDelayLabel,
            'actualArrivalDelayLabel' => $actualArrivalDelayLabel,
            'delayDepartureBandLabel' => $delayBandOptions[$delayDepartureBand] ?? '',
            'arrivalDelayMinutes' => $arrivalDelayMinutes,
            'airDelayThresholdHours' => $airDelayThresholdHours,
            'hasExpenses' => $hasExpenses,
            'incidentExpenseFlag' => $incidentExpenseFlag,
            'backendExpenseFlag' => $backendExpenseFlag,
            'needsDocuments' => $needsDocs,
            'createdAt' => (string)($meta[$caseCreatedAtKey] ?? ''),
            'ticketFiles' => $ticketFiles,
            'ticketAnalysis' => $ticketAnalysis,
            'refundReceiptFiles' => $refundReceiptFiles,
            'careReceiptFiles' => $careReceiptFiles,
            'refundExpenseItems' => $refundExpenseItems,
            'careExpenseItems' => $careExpenseItems,
            'currencyOptions' => $currencyOptions,
            'compFallbackReasons' => $compFallbackReasons,
            'hasDetails' => $hasDetails,
            'supportSummary' => $supportSummary,
            'showRefundStep' => $showRefundStep,
            'showSupportStep' => $showSupportStep,
            'isDelayRefundContext' => $isDelayRefundContext,
            'isCancellationRefundContext' => $isCancellationRefundContext,
            'showCancellationCompensationDetails' => $showCancellationCompensationDetails,
            'showBackendArticle8OfferField' => $showBackendArticle8OfferField,
            'operatorExceptionalTypeLabel' => $extraordinaryTypeLabels[$operatorExceptionalType] ?? $operatorExceptionalType,
            'article7EligibilityStatus' => $article7EligibilityStatus,
            'article7ReductionStatus' => $article7ReductionStatus,
            'article7BaseAmountEur' => $article7BaseAmountEur,
            'article7FinalAmountEur' => $article7FinalAmountEur,
            'extraordinaryScore' => $airRights['extraordinary_score'] ?? null,
            'extraordinaryBand' => (string)($airRights['extraordinary_band'] ?? ''),
            'manualReviewRequired' => !empty($airRights['manual_review_required']),
            'ferryRights' => $ferryRights,
            'ferryPmrRights' => $ferryPmrRights,
            'ferryOperationalEvidence' => $ferryOperationalEvidence,
            'railSelectedDeparture' => $railSelectedDeparture,
            'railOperationalEvidence' => $railOperationalEvidence,
            'railIncidentSeed' => $railIncidentSeed,
            'isVoluntaryDeniedBoarding' => $isVoluntaryDeniedBoarding,
            'deniedBoardingSelfArrangedFacts' => $deniedBoardingSelfArrangedFacts,
            'showDeniedBoardingCompensationDetails' => $showDeniedBoardingCompensationDetails,
            'deniedBoardingReductionRelevant' => $deniedBoardingReductionRelevant,
            'steps' => $steps,
            'activeStep' => $activeStep,
            'progressCompleted' => $progressCompleted,
            'progressTotal' => $progressTotal,
            'progressPct' => $progressPct,
            'sections' => [
                ...($showRefundStep ? [[
                    'title' => 'Afhjaelpning',
                    'status' => $refundStepDone ? 'Paabegyndt' : 'Mangler',
                    'summary' => $isOngoing
                        ? ($isFerryCase
                            ? 'Her samles ferry Art. 18 refund-, retur- og ombookingsudgifter, som liveflowet holder korte.'
                            : ($isRailCase
                                ? 'Her samles rail Art. 18-refusion, retur og omlaegningsudgifter, som liveflowet holder korte.'
                                : 'Her samles de detaljerede refund-, retur- og ombookingsudgifter, som liveflowet holder korte.'))
                        : ($isFerryCase
                            ? 'Ferry Art. 18, refund scope, ombooking og PMR Art. 8(3)-remedy holdes som selvstaendigt backend-spor.'
                            : ($isRailCase
                                ? 'Rail Art. 18, refusionsscope og omlaegning holdes som selvstaendigt backend-spor.'
                                : 'Article 8, refund scope, selvarrangeret loesning og retur til foerste afgangssted.')),
                ]] : []),
                ...($showSupportStep ? [[
                    'title' => 'Assistance, handicap og udgifter',
                    'status' => $supportStatus === 'done' ? 'Delvist udfyldt' : 'Mangler',
                    'summary' => $supportSummary,
                ]] : []),
                [
                    'title' => 'Billetter og dokumenter',
                    'status' => $ticketFiles !== [] ? 'Delvist udfyldt' : 'Mangler',
                    'summary' => $isFerryCase
                        ? 'Billet, booking og operatoer-/havnebevis holdes op mod de allerede valgte ferry-data.'
                        : ($isRailCase
                            ? 'Billet, booking og togdata holdes op mod den valgte rail-afgang og rail-operational evidence.'
                            : 'Billet kan flyttes til backend i stedet for at ligge i det korte frontflow, og uploaden holdes op mod de allerede oplyste air-data.'),
                ],
                [
                    'title' => 'Ansoeger og udbetaling',
                    'status' => $hasApplicant ? 'Paabegyndt' : 'Mangler',
                    'summary' => 'Kontaktdata fra frontflowet kan suppleres med adresse og udbetalingsoplysninger her.',
                ],
                [
                    'title' => 'Samtykke',
                    'status' => $hasConsent ? 'Paabegyndt' : 'Mangler',
                    'summary' => 'Fuldmagt, samtykke og ekstra info afsluttes her.',
                ],
            ],
        ];
    }

    private function buildPassengerCaseUrl(string $step = '', bool $fullBase = false): string
    {
        $params = [];
        $ref = $this->currentPassengerCaseRef();
        if ($ref !== '') {
            $params['ref'] = $ref;
        }
        if ($step !== '') {
            $params['step'] = $step;
        }

        return Router::url([
            'controller' => 'Passenger',
            'action' => 'case',
            '?' => $params,
        ], $fullBase);
    }

    private function currentPassengerCaseRef(): string
    {
        $queryRef = trim((string)($this->request->getQuery('ref') ?? ''));
        if ($queryRef !== '') {
            return $queryRef;
        }

        $session = $this->request->getSession();
        $ferryRef = trim((string)($session->read('flow.meta.ferry_case_ref') ?? ''));
        if ($ferryRef !== '') {
            return $ferryRef;
        }

        $railRef = trim((string)($session->read('flow.meta.rail_case_ref') ?? ''));
        if ($railRef !== '') {
            return $railRef;
        }

        return trim((string)($session->read('flow.meta.air_case_ref') ?? ''));
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function storeCaseUploads(array $meta, array $form): array
    {
        try {
            $ticketUpload = $this->request->getUploadedFile('air_backend_ticket_upload');
        } catch (\Throwable) {
            $ticketUpload = null;
        }
        try {
            $allUploads = $this->request->getUploadedFiles();
        } catch (\Throwable) {
            $allUploads = [];
        }

        $ticketFiles = (array)($meta['air_backend_ticket_files'] ?? []);
        $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'passenger_case';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        if ($ticketUpload instanceof UploadedFileInterface && $ticketUpload->getError() === UPLOAD_ERR_OK) {
            $saved = $this->movePassengerUpload($ticketUpload, $uploadDir, 'ticket');
            if ($saved !== null) {
                $ticketFiles[] = $saved;
                $localPath = $this->resolvePassengerUploadLocalPath((string)($saved['path'] ?? ''));
                if ($localPath !== null) {
                    $meta['air_backend_ticket_analysis'] = $this->analyzePassengerTicketFile($localPath, $saved, $form, $meta);
                }
            }
        }

        $meta['air_backend_ticket_files'] = $ticketFiles;
        if (empty($meta['air_backend_ticket_analysis']) && $ticketFiles !== []) {
            $last = (array)end($ticketFiles);
            $localPath = $this->resolvePassengerUploadLocalPath((string)($last['path'] ?? ''));
            if ($localPath !== null) {
                $meta['air_backend_ticket_analysis'] = $this->analyzePassengerTicketFile($localPath, $last, $form, $meta);
            }
        }

        return $meta;
    }

    /**
     * @param array<int|string,mixed> $postedItems
     * @param array<int|string,mixed> $uploadedItems
     * @param array<int|string,mixed> $existingItems
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
     */
    private function normalizeCaseExpenseItems(array $postedItems, array $uploadedItems, array $existingItems): array
    {
        $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'passenger_case';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $rows = [];
        foreach ($postedItems as $index => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $existing = isset($existingItems[$index]) && is_array($existingItems[$index]) ? (array)$existingItems[$index] : [];
            $item = [
                'type' => trim((string)($raw['type'] ?? '')),
                'amount' => trim((string)($raw['amount'] ?? '')),
                'currency' => trim((string)($raw['currency'] ?? '')),
                'description' => trim((string)($raw['description'] ?? '')),
            ];
            $receipt = isset($existing['receipt']) && is_array($existing['receipt']) ? (array)$existing['receipt'] : [];
            $upload = $uploadedItems[$index] ?? null;
            if ($upload instanceof UploadedFileInterface && $upload->getError() === UPLOAD_ERR_OK) {
                $saved = $this->movePassengerUpload($upload, $uploadDir, 'expense_receipt');
                if ($saved !== null) {
                    $receipt = $saved;
                }
            }
            if ($receipt !== []) {
                $item['receipt'] = $receipt;
            }
            if ($item['type'] === '' && $item['amount'] === '' && $item['currency'] === '' && $item['description'] === '' && $receipt === []) {
                continue;
            }
            $rows[] = $item;
        }

        $receiptFiles = array_values(array_filter(array_map(static function (array $item): ?array {
            $receipt = (array)($item['receipt'] ?? []);
            return isset($receipt['path'], $receipt['name']) ? $receipt : null;
        }, $rows)));

        return [$rows, $receiptFiles];
    }

    /**
     * @param array<int|string,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private function normalizeStoredExpenseItems(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = [
                'type' => trim((string)($item['type'] ?? '')),
                'amount' => trim((string)($item['amount'] ?? '')),
                'currency' => trim((string)($item['currency'] ?? '')),
                'description' => trim((string)($item['description'] ?? '')),
                'receipt' => isset($item['receipt']) && is_array($item['receipt']) ? (array)$item['receipt'] : [],
            ];
        }
        return $rows;
    }

    /**
     * @param array<int|string,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private function normalizeFrontendReturnExpenseSeedItems(array $items): array
    {
        $legacyTransportLabels = [
            'flight' => 'Fly',
            'rail' => 'Tog',
            'bus' => 'Bus',
            'taxi' => 'Taxi',
            'rideshare' => 'Samkoersel/rideshare',
        ];
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rawType = trim((string)($item['type'] ?? ''));
            $type = match ($rawType) {
                'return_to_origin',
                'new_ticket',
                'airport_transfer',
                'other_transport',
                'expensive_solution',
                'other' => $rawType,
                'flight',
                'rail',
                'bus',
                'taxi',
                'rideshare' => 'return_to_origin',
                default => ($rawType !== '' ? 'other' : ''),
            };
            $amount = trim((string)($item['amount'] ?? ''));
            $currency = trim((string)($item['currency'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
            if ($description === '' && isset($legacyTransportLabels[$rawType])) {
                $description = 'Transporttype fra frontend: ' . $legacyTransportLabels[$rawType];
            }
            if ($type === '' && $amount === '' && $currency === '' && $description === '') {
                continue;
            }
            $rows[] = [
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'receipt' => [],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
     */
    private function splitExpenseItemsByKind(array $items): array
    {
        $refundTypes = ['return_to_origin', 'new_ticket', 'airport_transfer', 'other_transport', 'expensive_solution', 'other'];
        $careTypes = ['meal', 'hotel', 'hotel_transport'];
        $refund = [];
        $care = [];
        foreach ($items as $item) {
            $type = trim((string)($item['type'] ?? ''));
            if (in_array($type, $careTypes, true)) {
                $care[] = $item;
                continue;
            }
            if ($type === '' || in_array($type, $refundTypes, true)) {
                $refund[] = $item;
            }
        }

        return [$refund, $care];
    }

    /**
     * @param array<string,mixed> $form
     * @param array<int,array<string,mixed>> $items
     * @return void
     */
    private function applyCaseExpenseItemsToLegacyFields(array &$form, array $items): void
    {
        foreach ([
            'return_to_origin_amount',
            'return_to_origin_currency',
            'air_reroute_expense_type',
            'air_reroute_expense_amount',
            'air_reroute_expense_currency',
            'air_reroute_expense_description',
            'meal_self_paid_amount',
            'meal_self_paid_currency',
            'hotel_self_paid_amount',
            'hotel_self_paid_currency',
            'hotel_transport_self_paid_amount',
            'hotel_transport_self_paid_currency',
        ] as $key) {
            unset($form[$key]);
        }

        $sumByType = [];
        $currencyByType = [];
        $descriptions = [];
        foreach ($items as $item) {
            $type = trim((string)($item['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $amount = (float)str_replace(',', '.', trim((string)($item['amount'] ?? '')));
            if (!isset($sumByType[$type])) {
                $sumByType[$type] = 0.0;
            }
            $sumByType[$type] += $amount;
            if (!isset($currencyByType[$type]) && trim((string)($item['currency'] ?? '')) !== '') {
                $currencyByType[$type] = trim((string)($item['currency'] ?? ''));
            }
            if (trim((string)($item['description'] ?? '')) !== '') {
                $descriptions[$type] = trim((string)($item['description'] ?? ''));
            }
        }

        if (isset($sumByType['return_to_origin'])) {
            $form['return_to_origin_expense'] = 'yes';
            $form['return_to_origin_amount'] = (string)$sumByType['return_to_origin'];
            $form['return_to_origin_currency'] = (string)($currencyByType['return_to_origin'] ?? '');
        }

        $rerouteTypes = ['new_ticket', 'airport_transfer', 'other_transport', 'expensive_solution', 'other'];
        foreach ($rerouteTypes as $type) {
            if (!isset($sumByType[$type])) {
                continue;
            }
            $form['air_reroute_expenses_incurred'] = 'yes';
            $form['air_reroute_expense_type'] = $type;
            $form['air_reroute_expense_amount'] = (string)$sumByType[$type];
            $form['air_reroute_expense_currency'] = (string)($currencyByType[$type] ?? '');
            $form['air_reroute_expense_description'] = (string)($descriptions[$type] ?? '');
            break;
        }

        if (isset($sumByType['meal'])) {
            $form['meal_self_paid_amount'] = (string)$sumByType['meal'];
            $form['meal_self_paid_currency'] = (string)($currencyByType['meal'] ?? '');
        }
        if (isset($sumByType['hotel'])) {
            $form['hotel_self_paid_amount'] = (string)$sumByType['hotel'];
            $form['hotel_self_paid_currency'] = (string)($currencyByType['hotel'] ?? '');
        }
        if (isset($sumByType['hotel_transport'])) {
            $form['hotel_transport_self_paid_amount'] = (string)$sumByType['hotel_transport'];
            $form['hotel_transport_self_paid_currency'] = (string)($currencyByType['hotel_transport'] ?? '');
        }
    }

    private function movePassengerUpload(UploadedFileInterface $upload, string $uploadDir, string $prefix): ?array
    {
        $name = (string)$upload->getClientFilename();
        if ($name === '') {
            return null;
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?: 'upload.bin';
        $targetName = uniqid($prefix . '_', true) . '_' . $safe;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $targetName;
        try {
            $upload->moveTo($target);
        } catch (\Throwable) {
            return null;
        }

        return [
            'path' => '/files/uploads/passenger_case/' . rawurlencode($targetName),
            'name' => $name,
            'uploaded_at' => date('c'),
        ];
    }

    private function resolvePassengerUploadLocalPath(string $publicPath): ?string
    {
        $trimmed = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicPath), DIRECTORY_SEPARATOR);
        if ($trimmed === '') {
            return null;
        }
        $candidate = WWW_ROOT . $trimmed;
        return is_file($candidate) ? $candidate : null;
    }

    /**
     * @param array<string,mixed> $savedFile
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function analyzePassengerTicketFile(string $path, array $savedFile, array $form, array $meta): array
    {
        [$text, $ocrLogs] = $this->extractPassengerTicketText($path);
        $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$text) ?? (string)$text;
        $transportMode = strtolower(trim((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'air'))));
        if (!in_array($transportMode, ['air', 'ferry'], true)) {
            $transportMode = 'air';
        }

        $analysis = [
            'file' => (string)($savedFile['name'] ?? basename($path)),
            'uploaded_at' => (string)($savedFile['uploaded_at'] ?? date('c')),
            'status' => $text !== '' ? 'analyzed' : 'uploaded_only',
            'transport_mode' => $transportMode,
            'ocr_logs' => $ocrLogs,
            'extractor_logs' => [],
            'segment_logs' => [],
            'match_checks' => [],
            'extracted' => [],
            'segments' => [],
            'contract_summary' => [],
            'needs_manual_review' => 'yes',
        ];

        if ($text === '') {
            $analysis['summary'] = 'Billet er uploadet, men tekst kunne ikke udtrækkes. Kræver manuel kontrol.';
            return $analysis;
        }

        $ticketParser = new TicketParseService();
        $baseSegments = $ticketParser->parseSegmentsFromText($text);
        $identifiers = $ticketParser->extractIdentifiers($text);
        $dates = $ticketParser->extractDates($text);

        $extract = $this->buildPassengerExtractorBroker()->run($text);
        $analysis['extractor_logs'] = (array)($extract->logs ?? []);
        $fields = array_filter((array)($extract->fields ?? []), static fn($v): bool => $v !== null && $v !== '');

        $segmentResult = (new LlmSegmentsExtractor())->extractSegments($text, true);
        $llmSegments = array_values(array_filter((array)($segmentResult['segments'] ?? []), 'is_array'));
        $analysis['segment_logs'] = (array)($segmentResult['logs'] ?? []);
        $segments = $llmSegments !== [] ? $llmSegments : array_values(array_filter($baseSegments, 'is_array'));
        if ($transportMode === 'ferry') {
            return $this->buildFerryPassengerTicketAnalysis($analysis, $form, $meta, $fields, $dates, $identifiers, $segments);
        }

        $extracted = [
            'dep_station' => (string)($fields['dep_station'] ?? ''),
            'arr_station' => (string)($fields['arr_station'] ?? ''),
            'dep_date' => (string)($fields['dep_date'] ?? ($dates[0] ?? '')),
            'dep_time' => (string)($fields['dep_time'] ?? ''),
            'arr_time' => (string)($fields['arr_time'] ?? ''),
            'operator' => (string)($fields['operator'] ?? ($fields['operating_carrier'] ?? $fields['marketing_carrier'] ?? '')),
            'marketing_carrier' => (string)($fields['marketing_carrier'] ?? ''),
            'operating_carrier' => (string)($fields['operating_carrier'] ?? ''),
            'flight_number' => (string)($fields['train_no'] ?? $fields['flight_number'] ?? ''),
            'pnr' => (string)($identifiers['pnr'] ?? ''),
        ];
        $analysis['extracted'] = $extracted;
        $analysis['segments'] = $segments;

        $resolverFlow = [
            'form' => array_filter([
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'ticket_upload_mode' => 'ticket',
                'dep_station' => $extracted['dep_station'] !== '' ? $extracted['dep_station'] : (string)($form['dep_station'] ?? ''),
                'arr_station' => $extracted['arr_station'] !== '' ? $extracted['arr_station'] : (string)($form['arr_station'] ?? ''),
                'dep_date' => $extracted['dep_date'] !== '' ? $extracted['dep_date'] : (string)($form['dep_date'] ?? ''),
                'operator' => $extracted['operator'] !== '' ? $extracted['operator'] : (string)($form['operator'] ?? ''),
                'marketing_carrier' => $extracted['marketing_carrier'],
                'operating_carrier' => $extracted['operating_carrier'],
                'air_connection_type' => (string)($form['air_connection_type'] ?? ''),
            ], static fn($v): bool => $v !== ''),
            'meta' => [
                '_segments_auto' => $segments,
            ],
            'journey' => [
                'segments' => $segments,
            ],
        ];
        $multimodal = (new MultimodalFlowResolver())->evaluate($resolverFlow);
        $airContract = (array)($multimodal['air_contract'] ?? []);
        $analysis['contract_summary'] = [
            'transport_mode' => 'air',
            'topology' => (string)($airContract['contract_topology'] ?? (($multimodal['contract_meta']['contract_topology'] ?? ''))),
            'connection_type' => (string)($airContract['air_connection_type'] ?? ''),
            'decision_basis' => (string)($airContract['decision_basis'] ?? ''),
            'manual_review_required' => !empty($airContract['manual_review_required']) ? 'yes' : 'no',
        ];

        $analysis['match_checks'] = $this->buildPassengerTicketMatchChecks($form, $meta, $extracted, $segments);
        $analysis['needs_manual_review'] = $this->analysisNeedsManualReview($analysis['match_checks'], $analysis['contract_summary']) ? 'yes' : 'no';
        $analysis['summary'] = $analysis['needs_manual_review'] === 'yes'
            ? 'Billet er analyseret, men der er afvigelser eller kontraktusikkerhed som kræver kontrol.'
            : 'Billet matcher i store træk de oplysninger, der blev givet i frontflowet.';

        return $analysis;
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $fields
     * @param array<int,string> $dates
     * @param array<string,mixed> $identifiers
     * @param array<int,array<string,mixed>> $segments
     * @return array<string,mixed>
     */
    private function buildFerryPassengerTicketAnalysis(
        array $analysis,
        array $form,
        array $meta,
        array $fields,
        array $dates,
        array $identifiers,
        array $segments
    ): array {
        $bookingReference = trim((string)($fields['booking_reference'] ?? ($identifiers['pnr'] ?? '')));
        $ticketNumber = trim((string)($fields['ticket_no'] ?? ''));
        $serviceCode = trim((string)($fields['service_code'] ?? ($fields['train_no'] ?? '')));
        $extracted = [
            'dep_station' => (string)($fields['dep_station'] ?? ''),
            'arr_station' => (string)($fields['arr_station'] ?? ''),
            'dep_date' => (string)($fields['dep_date'] ?? ($dates[0] ?? '')),
            'dep_time' => (string)($fields['dep_time'] ?? ''),
            'arr_time' => (string)($fields['arr_time'] ?? ''),
            'operator' => (string)($fields['operator'] ?? ''),
            'ticket_no' => $ticketNumber,
            'booking_reference' => $bookingReference,
            'price' => (string)($fields['price'] ?? ''),
            'vessel_name' => (string)($fields['vessel_name'] ?? ''),
            'service_code' => $serviceCode,
        ];
        $analysis['extracted'] = $extracted;
        $analysis['segments'] = $segments;

        $resolverFlow = [
            'form' => array_filter([
                'transport_mode' => 'ferry',
                'gating_mode' => 'ferry',
                'ticket_upload_mode' => 'ticket',
                'dep_station' => $extracted['dep_station'] !== '' ? $extracted['dep_station'] : (string)($form['dep_station'] ?? ''),
                'arr_station' => $extracted['arr_station'] !== '' ? $extracted['arr_station'] : (string)($form['arr_station'] ?? ''),
                'dep_date' => $extracted['dep_date'] !== '' ? $extracted['dep_date'] : (string)($form['dep_date'] ?? ''),
                'dep_time' => $extracted['dep_time'],
                'arr_time' => $extracted['arr_time'],
                'operator' => $extracted['operator'] !== '' ? $extracted['operator'] : (string)($form['operator'] ?? ''),
                'ticket_no' => $ticketNumber !== '' ? $ticketNumber : ($bookingReference !== '' ? $bookingReference : (string)($form['ticket_no'] ?? '')),
                'booking_reference' => $bookingReference !== '' ? $bookingReference : (string)($form['booking_reference'] ?? ''),
                'ferry_vessel_name' => $extracted['vessel_name'] !== '' ? $extracted['vessel_name'] : (string)($form['ferry_vessel_name'] ?? ''),
            ], static fn($v): bool => $v !== ''),
            'meta' => [
                '_segments_auto' => $segments,
            ],
            'journey' => [
                'segments' => $segments,
                'bookingRef' => $bookingReference !== '' ? $bookingReference : $ticketNumber,
            ],
        ];
        $multimodal = (new MultimodalFlowResolver())->evaluate($resolverFlow);
        $ferryContract = (array)($multimodal['ferry_contract'] ?? []);
        $contractDecision = (array)($multimodal['contract_decision'] ?? []);
        $analysis['contract_summary'] = [
            'transport_mode' => 'ferry',
            'topology' => (string)($contractDecision['contract_label'] ?? ($ferryContract['contract_topology'] ?? (($multimodal['contract_meta']['contract_topology'] ?? '')))),
            'connection_type' => (string)($contractDecision['ticket_scope'] ?? ''),
            'scope' => (string)($contractDecision['ticket_scope'] ?? ''),
            'decision_basis' => (string)($ferryContract['decision_basis'] ?? ($contractDecision['basis'] ?? '')),
            'manual_review_required' => (!empty($ferryContract['manual_review_required']) || !empty($contractDecision['manual_review_reasons'])) ? 'yes' : 'no',
        ];

        $analysis['match_checks'] = $this->buildPassengerFerryTicketMatchChecks($form, $meta, $extracted);
        $analysis['needs_manual_review'] = $this->analysisNeedsManualReview($analysis['match_checks'], $analysis['contract_summary']) ? 'yes' : 'no';
        $analysis['summary'] = $analysis['needs_manual_review'] === 'yes'
            ? 'Billet er analyseret, men der er afvigelser eller ferry-kontraktusikkerhed som kraever kontrol.'
            : 'Billet matcher i store traek den valgte faergeafgang og ferry-oplysningerne.';

        return $analysis;
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function extractPassengerTicketText(string $path): array
    {
        $text = '';
        $logs = [];
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        try {
            if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $text = trim((string)($pdf->getText() ?? ''));
                if ($text !== '') {
                    $logs[] = 'OCR: pdfparser';
                }
            }
            if ($text === '' && $ext === 'pdf') {
                $pdftotext = (function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH')) ?: 'pdftotext';
                $out = @shell_exec(escapeshellarg((string)$pdftotext) . ' -layout ' . escapeshellarg($path) . ' - 2>&1');
                if (is_string($out) && trim($out) !== '') {
                    $text = trim($out);
                    $logs[] = 'OCR: pdftotext';
                }
            } elseif (in_array($ext, ['txt', 'text'], true)) {
                $text = trim((string)@file_get_contents($path));
                if ($text !== '') {
                    $logs[] = 'OCR: txt';
                }
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                if (!$tess || $tess === '') {
                    $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                    $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                }
                $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                $out = @shell_exec(escapeshellarg((string)$tess) . ' ' . escapeshellarg($path) . ' stdout -l ' . escapeshellarg((string)$langs) . ' 2>&1');
                if (is_string($out) && trim($out) !== '') {
                    $text = trim($out);
                    $logs[] = 'OCR: tesseract';
                } else {
                    $visionEnabled = strtolower((string)((function_exists('env') ? env('LLM_VISION_ENABLED') : getenv('LLM_VISION_ENABLED')) ?? '0')) === '1';
                    if ($visionEnabled) {
                        [$visionText, $visionLogs] = (new \App\Service\Ocr\LlmVisionOcr())->extractTextFromImage($path);
                        if (is_string($visionText) && trim($visionText) !== '') {
                            $text = trim($visionText);
                            $logs = array_merge($logs, (array)$visionLogs);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $logs[] = 'OCR exception: ' . $e->getMessage();
        }

        return [$text, $logs];
    }

    private function buildPassengerExtractorBroker(): ExtractorBroker
    {
        return new ExtractorBroker([
            new LlmExtractor(),
            new HeuristicsExtractor(),
        ], 0.66, false);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,string> $extracted
     * @param array<int,array<string,mixed>> $segments
     * @return array<int,array<string,string>>
     */
    private function buildPassengerTicketMatchChecks(array $form, array $meta, array $extracted, array $segments): array
    {
        $checks = [];
        $currentRoute = [
            'dep' => trim((string)($form['dep_station'] ?? '')),
            'arr' => trim((string)($form['arr_station'] ?? '')),
        ];
        $routeStatus = 'unknown';
        if ($currentRoute['dep'] !== '' && $currentRoute['arr'] !== '' && $extracted['dep_station'] !== '' && $extracted['arr_station'] !== '') {
            $routeStatus = ($this->normalizeCompareText($currentRoute['dep']) === $this->normalizeCompareText($extracted['dep_station'])
                && $this->normalizeCompareText($currentRoute['arr']) === $this->normalizeCompareText($extracted['arr_station'])) ? 'match' : 'mismatch';
        } elseif ($extracted['dep_station'] !== '' || $extracted['arr_station'] !== '') {
            $routeStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Rute',
            'status' => $routeStatus,
            'current' => trim(implode(' -> ', array_filter([$currentRoute['dep'], $currentRoute['arr']]))),
            'detected' => trim(implode(' -> ', array_filter([$extracted['dep_station'], $extracted['arr_station']]))),
        ];

        $currentDate = trim((string)($form['dep_date'] ?? ''));
        $dateStatus = 'unknown';
        if ($currentDate !== '' && $extracted['dep_date'] !== '') {
            $dateStatus = $this->normalizeDateForCompare($currentDate) === $this->normalizeDateForCompare($extracted['dep_date']) ? 'match' : 'mismatch';
        } elseif ($extracted['dep_date'] !== '') {
            $dateStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Dato',
            'status' => $dateStatus,
            'current' => $currentDate,
            'detected' => $extracted['dep_date'],
        ];

        $selectedFlight = (array)($meta['air_selected_flight'] ?? []);
        $currentFlight = trim((string)($selectedFlight['flight_number'] ?? ''));
        $flightStatus = 'unknown';
        if ($currentFlight !== '' && $extracted['flight_number'] !== '') {
            $flightStatus = $this->normalizeCompareText($currentFlight) === $this->normalizeCompareText($extracted['flight_number']) ? 'match' : 'mismatch';
        } elseif ($extracted['flight_number'] !== '') {
            $flightStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Flynummer',
            'status' => $flightStatus,
            'current' => $currentFlight,
            'detected' => $extracted['flight_number'],
        ];

        $currentStopovers = $this->normalizeStopoverList((string)($form['air_stopover_airports'] ?? ''));
        $detectedStopovers = $this->detectSegmentStopovers($segments);
        $stopoverStatus = 'unknown';
        if ($currentStopovers !== [] || $detectedStopovers !== []) {
            $stopoverStatus = $currentStopovers === $detectedStopovers ? 'match' : (($currentStopovers === [] || $detectedStopovers === []) ? 'partial' : 'mismatch');
        }
        $checks[] = [
            'label' => 'Mellemlandinger',
            'status' => $stopoverStatus,
            'current' => $currentStopovers !== [] ? implode(', ', $currentStopovers) : 'Ingen oplyst',
            'detected' => $detectedStopovers !== [] ? implode(', ', $detectedStopovers) : 'Ingen fundet',
        ];

        return $checks;
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,string> $extracted
     * @return array<int,array<string,string>>
     */
    private function buildPassengerFerryTicketMatchChecks(array $form, array $meta, array $extracted): array
    {
        $selectedDeparture = (array)($meta['ferry_selected_departure'] ?? []);
        $opsEvidence = (array)($meta['ferry_operational_evidence'] ?? []);
        $currentRoute = [
            'dep' => trim((string)($selectedDeparture['departure_port_name'] ?? ($form['dep_station'] ?? ''))),
            'arr' => trim((string)($selectedDeparture['arrival_port_name'] ?? ($form['arr_station'] ?? ''))),
        ];
        $checks = [];

        $routeStatus = 'unknown';
        if ($currentRoute['dep'] !== '' && $currentRoute['arr'] !== '' && $extracted['dep_station'] !== '' && $extracted['arr_station'] !== '') {
            $routeStatus = ($this->normalizeCompareText($currentRoute['dep']) === $this->normalizeCompareText($extracted['dep_station'])
                && $this->normalizeCompareText($currentRoute['arr']) === $this->normalizeCompareText($extracted['arr_station'])) ? 'match' : 'mismatch';
        } elseif ($extracted['dep_station'] !== '' || $extracted['arr_station'] !== '') {
            $routeStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Rute',
            'status' => $routeStatus,
            'current' => trim(implode(' -> ', array_filter([$currentRoute['dep'], $currentRoute['arr']]))),
            'detected' => trim(implode(' -> ', array_filter([$extracted['dep_station'], $extracted['arr_station']]))),
        ];

        $currentDate = $this->extractDateFromDateTimeString((string)($selectedDeparture['scheduled_departure_local'] ?? ''));
        if ($currentDate === '') {
            $currentDate = trim((string)($form['dep_date'] ?? ''));
        }
        $dateStatus = 'unknown';
        if ($currentDate !== '' && $extracted['dep_date'] !== '') {
            $dateStatus = $this->normalizeDateForCompare($currentDate) === $this->normalizeDateForCompare($extracted['dep_date']) ? 'match' : 'mismatch';
        } elseif ($extracted['dep_date'] !== '') {
            $dateStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Dato',
            'status' => $dateStatus,
            'current' => $currentDate,
            'detected' => $extracted['dep_date'],
        ];

        $currentDepartureTime = $this->extractTimeFromDateTimeString((string)($selectedDeparture['scheduled_departure_local'] ?? ''));
        if ($currentDepartureTime === '') {
            $currentDepartureTime = $this->extractTimeFromDateTimeString((string)($opsEvidence['scheduled_departure_local'] ?? ''));
        }
        if ($currentDepartureTime === '') {
            $currentDepartureTime = trim((string)($form['dep_time'] ?? ''));
        }
        $departureStatus = 'unknown';
        if ($currentDepartureTime !== '' && $extracted['dep_time'] !== '') {
            $departureStatus = $currentDepartureTime === $extracted['dep_time'] ? 'match' : 'mismatch';
        } elseif ($extracted['dep_time'] !== '') {
            $departureStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Afgang',
            'status' => $departureStatus,
            'current' => $currentDepartureTime,
            'detected' => $extracted['dep_time'],
        ];

        $currentOperator = trim((string)($selectedDeparture['operator_name'] ?? ($opsEvidence['operator_name'] ?? ($form['operator'] ?? ''))));
        $operatorStatus = 'unknown';
        if ($currentOperator !== '' && $extracted['operator'] !== '') {
            $operatorStatus = $this->normalizeCompareText($currentOperator) === $this->normalizeCompareText($extracted['operator']) ? 'match' : 'mismatch';
        } elseif ($extracted['operator'] !== '') {
            $operatorStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Operator',
            'status' => $operatorStatus,
            'current' => $currentOperator,
            'detected' => $extracted['operator'],
        ];

        $currentVessel = trim((string)($selectedDeparture['vessel_name'] ?? ($opsEvidence['vessel_name'] ?? ($form['ferry_vessel_name'] ?? ''))));
        $vesselStatus = 'unknown';
        if ($currentVessel !== '' && $extracted['vessel_name'] !== '') {
            $vesselStatus = $this->normalizeCompareText($currentVessel) === $this->normalizeCompareText($extracted['vessel_name']) ? 'match' : 'mismatch';
        } elseif ($extracted['vessel_name'] !== '') {
            $vesselStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Fartoj',
            'status' => $vesselStatus,
            'current' => $currentVessel,
            'detected' => $extracted['vessel_name'],
        ];

        return $checks;
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $contractSummary
     */
    private function analysisNeedsManualReview(array $checks, array $contractSummary): bool
    {
        foreach ($checks as $check) {
            if ((string)($check['status'] ?? '') === 'mismatch') {
                return true;
            }
        }
        if (!empty($contractSummary['manual_review_required'])) {
            return true;
        }

        $transportMode = strtolower(trim((string)($contractSummary['transport_mode'] ?? 'air')));
        if ($transportMode === 'ferry') {
            $scope = trim((string)($contractSummary['scope'] ?? ($contractSummary['connection_type'] ?? '')));
            return $scope === '' || $scope === 'unknown' || $scope === 'unknown_manual_review';
        }

        return in_array((string)($contractSummary['connection_type'] ?? ''), ['', 'unknown', 'unknown_manual_review'], true);
    }

    /**
     * @param array<int,array<string,mixed>> $segments
     * @return array<int,string>
     */
    private function detectSegmentStopovers(array $segments): array
    {
        if (count($segments) < 2) {
            return [];
        }
        $stops = [];
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $stop = trim((string)($segments[$i]['to'] ?? ''));
            if ($stop !== '') {
                $stops[] = $stop;
            }
        }
        return array_values(array_unique($stops));
    }

    private function normalizeCompareText(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return preg_replace('/[^a-z0-9 ]/iu', '', $value) ?? $value;
    }

    private function normalizeDateForCompare(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $value;
    }

    private function extractDateFromDateTimeString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }
        if (preg_match('/(\d{2})-(\d{2})-(\d{4})/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return '';
    }

    private function extractTimeFromDateTimeString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/\b(\d{2}:\d{2})\b/', $value, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * @return array<int,string>
     */
    private function normalizeStopoverList(string $value): array
    {
        $parts = preg_split('/\s*,\s*/', trim($value)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    private function applyReliableTicketAnalysisToForm(array $form, array $meta): array
    {
        $analysis = (array)($meta['air_backend_ticket_analysis'] ?? []);
        if ($analysis === []) {
            return [$form, $meta];
        }

        $analysisMode = strtolower(trim((string)($analysis['transport_mode'] ?? ($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'air')))));
        if (in_array($analysisMode, ['ferry', 'rail'], true)) {
            $meta['air_backend_ticket_analysis']['applied_fields'] = [];
            $meta['air_backend_ticket_analysis']['auto_apply_disabled'] = 'yes';
            return [$form, $meta];
        }

        $extracted = (array)($analysis['extracted'] ?? []);
        $segments = array_values(array_filter((array)($analysis['segments'] ?? []), 'is_array'));
        $applied = (array)($analysis['applied_fields'] ?? []);
        $detectedStopovers = $this->detectSegmentStopovers($segments);
        $reverted = [];

        $revertExact = static function (array &$target, string $key, string $expected, array $applied, array &$reverted): void {
            if (!in_array($key, $applied, true) || $expected === '') {
                return;
            }
            if (trim((string)($target[$key] ?? '')) === $expected) {
                unset($target[$key]);
                $reverted[] = $key;
            }
        };

        $revertExact($form, 'dep_station', trim((string)($extracted['dep_station'] ?? '')), $applied, $reverted);
        $revertExact($form, 'arr_station', trim((string)($extracted['arr_station'] ?? '')), $applied, $reverted);
        $revertExact($form, 'dep_date', trim((string)($extracted['dep_date'] ?? '')), $applied, $reverted);
        $revertExact($form, 'operator', trim((string)($extracted['operator'] ?? '')), $applied, $reverted);
        $revertExact($form, 'operating_carrier', trim((string)($extracted['operating_carrier'] ?? '')), $applied, $reverted);
        $revertExact($form, 'marketing_carrier', trim((string)($extracted['marketing_carrier'] ?? '')), $applied, $reverted);

        if (in_array('air_stopover_airports', $applied, true)) {
            $currentStopovers = $this->normalizeStopoverList((string)($form['air_stopover_airports'] ?? ''));
            if ($currentStopovers === $detectedStopovers) {
                unset($form['air_stopover_airports']);
                $reverted[] = 'air_stopover_airports';
            }
        }

        if (in_array('air_route_type', $applied, true)) {
            $expectedRouteType = $detectedStopovers !== [] ? 'connecting' : 'direct';
            if (trim((string)($form['air_route_type'] ?? '')) === $expectedRouteType) {
                unset($form['air_route_type']);
                $reverted[] = 'air_route_type';
            }
        }

        if (in_array('air_connection_type', $applied, true) && trim((string)($form['air_connection_type'] ?? '')) !== '') {
            unset($form['air_connection_type']);
            $reverted[] = 'air_connection_type';
        }

        if ($reverted !== []) {
            unset($meta['air_stopover_seed']);
            $meta['logs'][] = 'Passenger backend ticket analysis kept advisory only; reverted auto-applied fields: ' . implode(', ', array_values(array_unique($reverted)));
        }

        $meta['air_backend_ticket_analysis']['applied_fields'] = [];
        $meta['air_backend_ticket_analysis']['auto_apply_disabled'] = 'yes';

        return [$form, $meta];
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'ja', 'on'], true);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $flags
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $compute
     * @param array<string,mixed> $incident
     * @return array<string,mixed>
     */
    private function persistPassengerCase(array $form, array $flags, array $meta, array $compute, array $incident): array
    {
        try {
            $meta = $this->refreshPassengerFlowMeta($form, $flags, $meta);
            $cases = $this->fetchTable('Cases');
            $transportMode = strtolower(trim((string)($form['transport_mode'] ?? '')));
            $isFerryCase = $transportMode === 'ferry';
            $isRailCase = $transportMode === 'rail';
            $caseIdKey = $isFerryCase ? 'ferry_case_id' : ($isRailCase ? 'rail_case_id' : 'air_case_id');
            $caseRefKey = $isFerryCase ? 'ferry_case_ref' : ($isRailCase ? 'rail_case_ref' : 'air_case_ref');
            $caseCreatedAtKey = $isFerryCase ? 'ferry_case_created_at' : ($isRailCase ? 'rail_case_created_at' : 'air_case_created_at');
            $caseId = (int)($meta[$caseIdKey] ?? 0);
            $case = $caseId > 0 ? $cases->find()->where(['id' => $caseId])->first() : null;
            if ($case === null) {
                $case = $cases->newEmptyEntity();
                $case->ref = (string)($meta[$caseRefKey] ?? Text::uuid());
            }

            $expenseItems = array_merge(
                (array)($form['air_case_refund_expense_items'] ?? []),
                (array)($form['air_case_care_expense_items'] ?? []),
                (array)($form['air_case_expense_items'] ?? [])
            );
            $expenseTotal = 0.0;
            foreach ($expenseItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $raw = trim((string)($item['amount'] ?? ''));
                if ($raw !== '') {
                    $expenseTotal += (float)preg_replace('/[^0-9.]/', '', $raw);
                }
            }
            if ($expenseTotal <= 0 && trim((string)($form['air_incident_expenses_total'] ?? '')) !== '') {
                $expenseTotal = (float)preg_replace('/[^0-9.]/', '', (string)$form['air_incident_expenses_total']);
            }

            $delayMinutes = null;
            if (isset($compute['delayMinEU']) && is_numeric($compute['delayMinEU'])) {
                $delayMinutes = (int)$compute['delayMinEU'];
            } elseif (isset($form['delayAtFinalMinutes']) && is_numeric($form['delayAtFinalMinutes'])) {
                $delayMinutes = (int)$form['delayAtFinalMinutes'];
            } elseif (isset($form['arrival_delay_minutes']) && is_numeric($form['arrival_delay_minutes'])) {
                $delayMinutes = (int)$form['arrival_delay_minutes'];
            }

            $snapshot = json_encode([
                'form' => $form,
                'flags' => $flags,
                'meta' => $meta,
                'compute' => $compute,
                'incident' => $incident,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $passengerName = trim((string)($form['firstName'] ?? '') . ' ' . (string)($form['lastName'] ?? ''));
            $operator = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
            $country = strtoupper(trim((string)($form['address_country'] ?? ($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? '')))));
            $currency = (string)($form['air_incident_expenses_currency'] ?? ($form['price_currency'] ?? 'EUR'));
            $compBand = (string)($form['air_distance_band'] ?? '');
            $compAmount = isset($form['air_compensation_amount']) && is_numeric($form['air_compensation_amount'])
                ? (float)$form['air_compensation_amount']
                : null;
            if ($isFerryCase) {
                $ferryRights = (array)($meta['_multimodal']['ferry_rights'] ?? []);
                $ferryBand = (string)($ferryRights['art19_comp_band'] ?? ($flags['ferry_art19_comp_band'] ?? ''));
                $compBand = in_array($ferryBand, ['25', '50'], true) ? $ferryBand : '';
                $ticketPriceRaw = trim((string)($form['price'] ?? ''));
                $ticketPrice = $ticketPriceRaw !== ''
                    ? (float)str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $ticketPriceRaw))
                    : 0.0;
                $compAmount = !empty($ferryRights['gate_art19']) && $compBand !== '' && $ticketPrice > 0
                    ? round($ticketPrice * ((float)$compBand / 100), 2)
                    : null;
            }

            $case = $cases->patchEntity($case, [
                'status' => 'open',
                'travel_date' => (string)($form['dep_date'] ?? '') !== '' ? (string)$form['dep_date'] : null,
                'passenger_name' => $passengerName !== '' ? $passengerName : null,
                'operator' => $operator !== '' ? $operator : null,
                'country' => $country !== '' ? $country : null,
                'delay_min_eu' => $delayMinutes,
                'remedy_choice' => (string)($form['remedyChoice'] ?? '') !== '' ? (string)$form['remedyChoice'] : null,
                'art20_expenses_total' => $expenseTotal > 0 ? $expenseTotal : null,
                'comp_band' => $compBand !== '' ? $compBand : null,
                'comp_amount' => $compAmount,
                'currency' => $currency !== '' ? $currency : null,
                'eu_only' => true,
                'extraordinary' => $this->truthy($form['operatorExceptionalCircumstances'] ?? ($form['extraordinary_circumstances'] ?? '')),
                'attachments_count' => count((array)($meta['air_backend_ticket_files'] ?? []))
                    + count((array)($meta['air_backend_refund_receipt_files'] ?? []))
                    + count((array)($meta['air_backend_care_receipt_files'] ?? []))
                    + count((array)($meta['air_backend_receipt_files'] ?? [])),
                'flow_snapshot' => $snapshot ?: null,
            ]);

            if ($saved = $cases->save($case)) {
                $meta[$caseIdKey] = (int)$saved->id;
                $meta[$caseRefKey] = (string)$saved->ref;
                if (empty($meta[$caseCreatedAtKey])) {
                    $meta[$caseCreatedAtKey] = date('c');
                }
            }
        } catch (\Throwable) {
            // Session flow must keep working even if DB persistence fails.
        }

        return $meta;
    }
}
