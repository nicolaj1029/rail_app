<?php
declare(strict_types=1);

namespace App\Service;

final class MultimodalFlowResolver
{
    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function evaluate(array $flow, bool $includeIncident = true): array
    {
        $modeClassification = (new ModeClassifier())->classify($flow);
        $transportMode = $this->detectTransportMode($flow);
        $journeyBasic = $this->extractJourneyBasic($flow);
        $segments = $this->extractSegments($flow);
        $ticketEvidence = $this->buildTicketEvidence($flow, $journeyBasic, $segments, $transportMode);
        $contractMeta = $this->buildContractMeta($flow, $journeyBasic, $segments, $transportMode, $ticketEvidence);
        $scopeMeta = $this->buildScopeMeta($flow, $transportMode);
        $incidentMeta = $this->buildIncidentMeta($flow, $transportMode);
        $contractMeta = array_replace($contractMeta, (array)($flow['contract_meta'] ?? []));
        $scopeMeta = array_replace($scopeMeta, (array)($flow['scope_meta'] ?? []));
        $incidentMeta = array_replace($incidentMeta, (array)($flow['incident_meta'] ?? []));

        $result = [
            'transport_mode' => $transportMode,
            'mode_classification' => $modeClassification,
            'ticket_extracts' => (array)($ticketEvidence['ticket_extracts'] ?? []),
            'grouped_contracts' => (array)($ticketEvidence['grouped_contracts'] ?? []),
            'contract_meta' => $contractMeta,
            'scope_meta' => $scopeMeta,
            'incident_meta' => $incidentMeta,
        ];

        $scopeResult = $scopeMeta;
        $contractResult = $contractMeta;
        $rightsResult = [];

        if ($transportMode === 'ferry') {
            $scope = (new FerryScopeResolver())->evaluate($scopeMeta);
            $contract = (new FerryContractResolver())->evaluate($contractMeta, $scope);
            $result['ferry_scope'] = $scope;
            $result['ferry_contract'] = $contract;
            if ($includeIncident) {
                $result['ferry_rights'] = (new FerryRightsEvaluator())->evaluate($incidentMeta, $scope, $contract);
                $result['ferry_pmr_rights'] = (new FerryPmrRightsEvaluator())->evaluate($incidentMeta, $scope, $contract);
            }
            $scopeResult = $scope;
            $contractResult = $contract;
            $rightsResult = array_merge(
                (array)($result['ferry_rights'] ?? []),
                (array)($result['ferry_pmr_rights'] ?? [])
            );
        } elseif ($transportMode === 'air') {
            $scope = (new AirScopeResolver())->evaluate($scopeMeta);
            $contract = (new AirContractResolver())->evaluate($contractMeta, $scope);
            $result['air_scope'] = $scope;
            $result['air_contract'] = $contract;
            if ($includeIncident) {
                $result['air_rights'] = (new AirRightsEvaluator())->evaluate($incidentMeta, $scope, $contract);
            }
            $scopeResult = $scope;
            $contractResult = $contract;
            $rightsResult = (array)($result['air_rights'] ?? []);
        } elseif ($transportMode === 'bus') {
            $scope = (new BusScopeResolver())->evaluate($scopeMeta);
            $contract = (new ModeContractResolver())->evaluate($transportMode, $contractMeta);
            $result['bus_scope'] = $scope;
            $result['bus_contract'] = $contract;
            if ($includeIncident) {
                $result['bus_rights'] = (new BusRightsEvaluator())->evaluate($incidentMeta, $scope, $contract);
                $result['bus_pmr_rights'] = (new BusPmrRightsEvaluator())->evaluate($incidentMeta, $scope);
            }
            $scopeResult = $scope;
            $contractResult = $contract;
            $rightsResult = array_merge(
                (array)($result['bus_rights'] ?? []),
                (array)($result['bus_pmr_rights'] ?? [])
            );
        }

        $result['claim_direction'] = $this->buildClaimDirection($transportMode, $contractMeta, $contractResult, $scopeResult, $rightsResult);
        $result['contract_decision'] = $this->buildContractDecision($transportMode, $contractMeta, $contractResult, $scopeResult);
        $result['canonical'] = $this->buildCanonicalModel(
            $flow,
            $transportMode,
            $journeyBasic,
            $segments,
            $ticketEvidence,
            $contractMeta,
            $scopeMeta,
            $incidentMeta,
            $scopeResult,
            $contractResult,
            $rightsResult,
            (array)$result['claim_direction'],
            (array)$result['contract_decision']
        );

        return $result;
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function detectTransportMode(array $flow): string
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $ticketMode = strtolower(trim((string)($form['ticket_upload_mode'] ?? '')));
        $modeSource = strtolower(trim((string)(
            $form['transport_mode_source']
            ?? $meta['transport_mode_source']
            ?? ''
        )));

        $candidate = strtolower(trim((string)(
            $form['transport_mode']
            ?? $meta['transport_mode']
            ?? $journey['transport_mode']
            ?? ''
        )));

        if ($this->isManualTransportModeAuthoritative($ticketMode, $modeSource)
            && in_array($candidate, ['ferry', 'bus', 'air', 'rail'], true)
        ) {
            return $candidate;
        }

        $classifiedMode = (new ModeClassifier())->resolvePrimaryMode($flow);
        if ($classifiedMode !== null) {
            return $classifiedMode;
        }

        return match ($candidate) {
            'ferry', 'bus', 'air', 'rail' => $candidate,
            default => $this->inferTransportMode($flow) ?? 'rail',
        };
    }

    private function isManualTransportModeAuthoritative(string $ticketMode, string $modeSource): bool
    {
        if (in_array($ticketMode, ['ticketless', 'seasonpass'], true)) {
            return true;
        }

        return $modeSource === 'manual';
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function inferTransportMode(array $flow): ?string
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);

        foreach ([
            $form['dep_station_lookup_mode'] ?? null,
            $form['arr_station_lookup_mode'] ?? null,
            $form['dep_terminal_lookup_mode'] ?? null,
            $form['arr_terminal_lookup_mode'] ?? null,
        ] as $lookupMode) {
            $candidate = strtolower(trim((string)$lookupMode));
            if (in_array($candidate, ['ferry', 'bus', 'air', 'rail'], true)) {
                return $candidate;
            }
        }

        $segments = (array)($journey['segments'] ?? ($meta['_segments_auto'] ?? []));
        $modeCounts = [];
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentMode = strtolower(trim((string)($segment['mode'] ?? '')));
            if (!in_array($segmentMode, ['ferry', 'bus', 'air', 'rail'], true)) {
                continue;
            }
            $modeCounts[$segmentMode] = (int)($modeCounts[$segmentMode] ?? 0) + 1;
        }
        if ($modeCounts !== []) {
            arsort($modeCounts);
            return (string)array_key_first($modeCounts);
        }

        $registry = new TransportOperatorRegistry();
        foreach ([
            $form['operator'] ?? null,
            $meta['_auto']['operator']['value'] ?? null,
            $form['incident_segment_operator'] ?? null,
            $form['operating_carrier'] ?? null,
            $form['marketing_carrier'] ?? null,
        ] as $operatorText) {
            $candidate = $registry->detectModeByName((string)$operatorText);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function extractJourneyBasic(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);

        return [
            'operator' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            'operator_country' => $form['operator_country'] ?? ($auto['operator_country']['value'] ?? null),
            'operator_product' => $form['operator_product'] ?? ($auto['operator_product']['value'] ?? null),
            'ticket_no' => $form['ticket_no'] ?? ($journey['bookingRef'] ?? null) ?? ($meta['_identifiers']['pnr'] ?? null),
            'dep_station' => $form['dep_station'] ?? ($auto['dep_station']['value'] ?? null),
            'arr_station' => $form['arr_station'] ?? ($auto['arr_station']['value'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<int,array<string,mixed>>
     */
    private function extractSegments(array $flow): array
    {
        $journey = (array)($flow['journey'] ?? []);
        $meta = (array)($flow['meta'] ?? []);

        $segments = (array)($journey['segments'] ?? []);
        if ($segments !== []) {
            return $segments;
        }

        return (array)($meta['_segments_auto'] ?? []);
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @param array<string,mixed> $ticketEvidence
     * @return array<string,mixed>
     */
    private function buildContractMeta(array $flow, array $journeyBasic, array $segments, string $transportMode, array $ticketEvidence): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $ticketMode = strtolower(trim((string)($form['ticket_upload_mode'] ?? 'ticket')));
        $isTicketless = in_array($ticketMode, ['ticketless', 'seasonpass'], true);

        $sellerChannel = strtolower(trim((string)($form['seller_channel'] ?? '')));
        $sharedBookingSelection = $this->normalizeYesNoMachine($form['shared_pnr_scope'] ?? ($meta['shared_pnr_scope'] ?? null));
        $sameTransactionSelection = $this->normalizeYesNoMachine($form['same_transaction'] ?? ($meta['same_transaction_all'] ?? null));
        $singleTxnOperator = $this->normalizeYesNoMachine($form['single_txn_operator'] ?? ($meta['single_txn_operator'] ?? null));
        $singleTxnRetailer = $this->normalizeYesNoMachine($form['single_txn_retailer'] ?? ($meta['single_txn_retailer'] ?? null));
        $throughDisclosure = $this->normalizeDisclosure($form['through_ticket_disclosure'] ?? ($meta['through_ticket_disclosure'] ?? null));
        $separateNotice = $this->normalizeSeparateNotice($form['separate_contract_notice'] ?? ($meta['separate_contract_notice'] ?? null));

        $sharedBookingReference = match ($sharedBookingSelection) {
            'yes' => true,
            'no' => false,
            default => $ticketEvidence['shared_booking_reference'] ?? null,
        };
        $singleTransaction = match (true) {
            $sameTransactionSelection === 'yes' => true,
            $sameTransactionSelection === 'no' => false,
            $singleTxnOperator === 'no' && $singleTxnRetailer === 'no' => false,
            default => null,
        };

        $sellerType = match ($sellerChannel) {
            'operator' => 'operator',
            'retailer' => 'ticket_vendor',
            'agency' => 'travel_agent',
            'tour_operator' => 'tour_operator',
            default => ($singleTxnRetailer === 'yes' ? 'ticket_vendor' : ($journeyBasic['operator'] ?? null ? 'operator' : 'unknown')),
        };

        $manualJourneyStructure = strtolower(trim((string)($form['journey_structure'] ?? '')));
        if (!in_array($manualJourneyStructure, ['single_segment', 'single_mode_connections', 'multimodal_connections'], true)) {
            $manualJourneyStructure = '';
        }
        $bookingCohesion = (string)($ticketEvidence['booking_cohesion'] ?? 'unknown');
        $serviceCohesion = (string)($ticketEvidence['service_cohesion'] ?? 'unknown');
        $manualReviewReasons = array_values(array_map('strval', (array)($ticketEvidence['manual_review_reasons'] ?? [])));
        $groupedContracts = (array)($ticketEvidence['grouped_contracts'] ?? []);
        $ticketExtracts = (array)($ticketEvidence['ticket_extracts'] ?? []);
        $journeyStructure = $manualJourneyStructure !== ''
            ? $manualJourneyStructure
            : $this->inferJourneyStructure($segments, $journeyBasic, $transportMode, $ticketExtracts);
        $originalContractMode = $this->resolveOriginalContractMode($form, $meta, $segments, $transportMode);
        $contractTopologyHint = null;
        $contractConfidence = 'low';

        $contractTopology = 'unknown_manual_review';
        if ($journeyStructure === 'single_segment') {
            $contractTopology = 'single_mode_single_contract';
            $contractConfidence = $isTicketless ? 'medium' : 'high';
        } elseif ($separateNotice === 'yes' && $throughDisclosure === 'separate') {
            $contractTopology = 'separate_contracts';
            $contractConfidence = $isTicketless ? 'medium' : 'high';
        } elseif ($singleTransaction === false) {
            $contractTopology = 'separate_contracts';
            $contractConfidence = $isTicketless ? 'medium' : 'high';
        } else {
            $positiveBookingLink = ($sharedBookingReference === true || $singleTransaction === true);
            $sameModeProtected = $journeyStructure === 'single_mode_connections'
                && $positiveBookingLink
                && $bookingCohesion === 'strong'
                && $separateNotice !== 'yes'
                && $serviceCohesion !== 'weak';
            if ($positiveBookingLink && $bookingCohesion === 'strong' && $separateNotice === 'no') {
                if ($isTicketless) {
                    $contractTopologyHint = 'likely_single_contract';
                    $contractConfidence = 'medium';
                    $manualReviewReasons[] = 'ticketless_estimate_only';
                } else {
                    $contractTopology = $journeyStructure === 'single_mode_connections'
                        ? 'protected_single_contract'
                        : 'single_multimodal_contract';
                    $contractConfidence = 'high';
                }
            } elseif ($sameModeProtected && !$isTicketless) {
                $contractTopology = 'protected_single_contract';
                $contractConfidence = $separateNotice === 'unclear' ? 'medium' : 'high';
            } elseif ($positiveBookingLink && in_array($bookingCohesion, ['medium', 'unknown'], true) && $separateNotice !== 'yes') {
                $contractTopologyHint = 'likely_single_contract';
                $contractConfidence = $isTicketless ? 'low' : 'medium';
                $manualReviewReasons[] = 'booking_cohesion_not_strong';
            } elseif ($separateNotice === 'yes' || $throughDisclosure === 'separate' || $bookingCohesion === 'weak') {
                $contractTopologyHint = 'likely_separate_contracts';
                $contractConfidence = $isTicketless ? 'low' : 'medium';
            }
        }

        if ($journeyStructure === 'unknown') {
            $manualReviewReasons[] = 'journey_structure_unknown';
        }
        if ($separateNotice === 'unclear' && $contractTopology === 'unknown_manual_review') {
            $manualReviewReasons[] = 'separate_contract_notice_unclear';
        }
        if ($throughDisclosure === 'unknown' && $journeyStructure === 'multimodal_connections') {
            $manualReviewReasons[] = 'contract_structure_disclosure_unknown';
        }
        if ($isTicketless && $journeyStructure !== 'single_segment' && !in_array('ticketless_estimate_only', $manualReviewReasons, true)) {
            $manualReviewReasons[] = 'ticketless_estimate_only';
        }
        if (count($groupedContracts) > 1) {
            $manualReviewReasons[] = 'multiple_ticket_groups';
        }
        if ($serviceCohesion === 'weak') {
            $manualReviewReasons[] = 'service_cohesion_weak';
        }
        $manualReviewReasons = array_values(array_unique(array_filter($manualReviewReasons)));

        $incidentSegmentMode = strtolower(trim((string)(
            $form['incident_segment_mode']
            ?? $meta['incident_segment_mode']
            ?? $transportMode
        )));
        if (!in_array($incidentSegmentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            $incidentSegmentMode = $transportMode;
        }
        $claimTransportMode = $contractTopology === 'separate_contracts'
            ? $incidentSegmentMode
            : ($originalContractMode ?? $transportMode);

        return [
            'seller_type' => $sellerType,
            'seller_name' => $journeyBasic['operator'] ?? null,
            'shared_booking_reference' => $sharedBookingReference,
            'single_transaction' => $singleTransaction,
            'contract_structure_disclosure' => $throughDisclosure,
            'separate_contract_notice' => $separateNotice,
            'original_contract_mode' => $originalContractMode,
            'journey_structure' => $journeyStructure,
            'contract_topology' => $contractTopology,
            'contract_topology_hint' => $contractTopologyHint,
            'contract_topology_confidence' => $contractConfidence,
            'booking_cohesion' => $bookingCohesion,
            'service_cohesion' => $serviceCohesion,
            'ticket_extract_count' => count($ticketExtracts),
            'grouped_contract_count' => count($groupedContracts),
            'incident_segment_mode' => $incidentSegmentMode,
            'incident_segment_operator' => $form['incident_segment_operator'] ?? $journeyBasic['operator'] ?? null,
            'primary_claim_party' => match ($contractTopology) {
                'separate_contracts' => 'segment_operator',
                'single_mode_single_contract', 'protected_single_contract', 'single_multimodal_contract' => 'seller',
                default => 'manual_review',
            },
            'claim_transport_mode' => $claimTransportMode,
            'rights_module' => $incidentSegmentMode,
            'manual_review_required' => $contractTopology === 'unknown_manual_review',
            'manual_review_reasons' => $manualReviewReasons,
            'same_pnr' => $this->normalizeNullableBool($form['same_pnr'] ?? null),
            'same_booking_reference' => $this->normalizeNullableBool($form['same_booking_reference'] ?? null),
            'same_eticket' => $this->normalizeNullableBool($form['same_eticket'] ?? null),
            'protected_connection_disclosed' => $this->normalizeNullableBool($form['protected_connection_disclosed'] ?? null),
            'self_transfer_notice' => $this->normalizeNullableBool($form['self_transfer_notice'] ?? null),
            'marketing_carrier' => $form['marketing_carrier'] ?? null,
            'operating_carrier' => $form['operating_carrier'] ?? null,
            'air_connection_type' => $form['air_connection_type'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @return array<string,mixed>
     */
    private function buildTicketEvidence(array $flow, array $journeyBasic, array $segments, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);
        $ticketMode = strtolower(trim((string)($form['ticket_upload_mode'] ?? 'ticket')));

        $extracts = [];
        $currentExtract = [
            'file' => (string)($form['_ticketFilename'] ?? ''),
            'pnr' => (string)($journey['bookingRef'] ?? ($meta['_identifiers']['pnr'] ?? '')),
            'dep_date' => (string)($auto['dep_date']['value'] ?? ($form['dep_date'] ?? '')),
            'operator' => (string)($journeyBasic['operator'] ?? ''),
            'segments' => $segments,
            'modes' => $this->extractModesFromSegments($segments, $transportMode),
            'has_price' => trim((string)($form['price'] ?? ($auto['price']['value'] ?? ''))) !== '',
            'has_itinerary' => count($segments) > 0 || (!empty($journeyBasic['dep_station']) && !empty($journeyBasic['arr_station'])),
            'self_transfer_notice' => $this->detectSelfTransferFromText((string)($meta['_ocr_text'] ?? '')),
            'protected_connection_notice' => $this->detectProtectedConnectionFromText((string)($meta['_ocr_text'] ?? '')),
        ];
        if ($ticketMode === 'ticket' && ($currentExtract['pnr'] !== '' || $currentExtract['dep_date'] !== '' || !empty($currentExtract['has_itinerary']))) {
            $extracts[] = $currentExtract;
        }

        foreach ((array)($meta['_multi_tickets'] ?? []) as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $ticketSegments = (array)($ticket['segments'] ?? []);
            $ticketAuto = (array)($ticket['auto'] ?? []);
            $extracts[] = [
                'file' => (string)($ticket['file'] ?? ''),
                'pnr' => (string)($ticket['pnr'] ?? ''),
                'dep_date' => (string)($ticket['dep_date'] ?? ''),
                'operator' => (string)($ticketAuto['operator']['value'] ?? ''),
                'segments' => $ticketSegments,
                'modes' => $this->extractModesFromSegments($ticketSegments, $transportMode),
                'has_price' => trim((string)($ticketAuto['price']['value'] ?? '')) !== '',
                'has_itinerary' => !empty($ticketSegments),
                'self_transfer_notice' => $this->detectSelfTransferFromText(implode("\n", array_map('strval', (array)($ticket['logs'] ?? [])))),
                'protected_connection_notice' => $this->detectProtectedConnectionFromText(implode("\n", array_map('strval', (array)($ticket['logs'] ?? [])))),
            ];
        }

        $normalizedForJoin = [];
        foreach ($extracts as $extract) {
            $normalizedForJoin[] = [
                'file' => (string)($extract['file'] ?? ''),
                'bookingRef' => (string)($extract['pnr'] ?? ''),
                'dep_date' => (string)($extract['dep_date'] ?? ''),
                'segments' => (array)($extract['segments'] ?? []),
                'passengers' => [],
                'operator' => (string)($extract['operator'] ?? ''),
            ];
        }
        $groupedContracts = $normalizedForJoin !== []
            ? (new TicketJoinService())->groupTickets($normalizedForJoin)
            : [];

        $pnrs = [];
        $operators = [];
        $modes = [];
        $depDates = [];
        $priceSignals = 0;
        $score = 0;
        $serviceScore = 0;
        $manualReviewReasons = [];
        $selfTransfer = false;
        $protectedConnection = false;

        foreach ($extracts as $extract) {
            $pnr = trim((string)($extract['pnr'] ?? ''));
            if ($pnr !== '') {
                $pnrs[$pnr] = true;
            }
            $operator = strtolower(trim((string)($extract['operator'] ?? '')));
            if ($operator !== '') {
                $operators[$operator] = true;
            }
            foreach ((array)($extract['modes'] ?? []) as $mode) {
                $mode = strtolower(trim((string)$mode));
                if (in_array($mode, ['rail', 'ferry', 'bus', 'air'], true)) {
                    $modes[$mode] = true;
                }
            }
            $depDate = trim((string)($extract['dep_date'] ?? ''));
            if ($depDate !== '') {
                $depDates[$depDate] = true;
            }
            if (!empty($extract['has_price'])) {
                $priceSignals++;
            }
            if (!empty($extract['self_transfer_notice'])) {
                $selfTransfer = true;
            }
            if (!empty($extract['protected_connection_notice'])) {
                $protectedConnection = true;
            }
        }

        $sharedBookingReference = null;
        if (count($pnrs) === 1 && $pnrs !== []) {
            $sharedBookingReference = true;
            $score += 2;
        } elseif (count($pnrs) > 1) {
            $sharedBookingReference = false;
            $score -= 1;
            $manualReviewReasons[] = 'multiple_booking_references';
        }

        if (count($groupedContracts) === 1 && count($extracts) > 1) {
            $score += 2;
            $serviceScore += 1;
        } elseif (count($groupedContracts) > 1) {
            $score -= 2;
            $serviceScore -= 1;
            $manualReviewReasons[] = 'multiple_ticket_groups';
        }
        if (count($operators) === 1 && $operators !== []) {
            $score += 1;
        }
        if (count($depDates) === 1 && $depDates !== []) {
            $score += 1;
        }
        if ($priceSignals > 0) {
            $score += 1;
        }
        if (count($segments) > 1 || (count($extracts) === 1 && !empty($extracts[0]['has_itinerary']))) {
            $score += 1;
            $serviceScore += 1;
        }
        if ($selfTransfer) {
            $score -= 3;
            $serviceScore -= 3;
            $manualReviewReasons[] = 'self_transfer_or_independent_segments_detected';
        }
        if ($protectedConnection) {
            $serviceScore += 3;
        }
        if (count($modes) > 1) {
            $serviceScore += count($groupedContracts) === 1 ? 1 : 0;
        }
        $protectedConnectionDisclosed = $this->normalizeNullableBool($form['protected_connection_disclosed'] ?? null);
        if ($protectedConnectionDisclosed === true) {
            $serviceScore += 2;
        } elseif ($protectedConnectionDisclosed === false) {
            $serviceScore -= 1;
        }
        $selfTransferNotice = $this->normalizeNullableBool($form['self_transfer_notice'] ?? null);
        if ($selfTransferNotice === true) {
            $serviceScore -= 3;
        } elseif ($selfTransferNotice === false) {
            $serviceScore += 1;
        }
        if ($ticketMode !== 'ticket') {
            $manualReviewReasons[] = 'ticketless_estimate_only';
        }

        $bookingCohesion = match (true) {
            $score >= 4 => 'strong',
            $score >= 2 => 'medium',
            $score <= -1 => 'weak',
            default => 'unknown',
        };
        $serviceCohesion = match (true) {
            $selfTransfer => 'weak',
            $ticketMode !== 'ticket' && $serviceScore === 0 => 'unknown',
            $serviceScore >= 4 => 'strong',
            $serviceScore >= 2 => 'medium',
            $serviceScore <= -1 => 'weak',
            default => 'unknown',
        };

        return [
            'ticket_extracts' => $extracts,
            'grouped_contracts' => $groupedContracts,
            'shared_booking_reference' => $sharedBookingReference,
            'booking_cohesion' => $bookingCohesion,
            'service_cohesion' => $serviceCohesion,
            'manual_review_reasons' => array_values(array_unique($manualReviewReasons)),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $segments
     * @return array<int,string>
     */
    private function extractModesFromSegments(array $segments, string $fallbackMode): array
    {
        $modes = [];
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $mode = strtolower(trim((string)($segment['mode'] ?? '')));
            if (in_array($mode, ['rail', 'ferry', 'bus', 'air'], true)) {
                $modes[$mode] = true;
            }
        }
        if ($modes === [] && in_array($fallbackMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            $modes[$fallbackMode] = true;
        }

        return array_keys($modes);
    }

    /**
     * @param array<int,array<string,mixed>> $segments
     * @param array<string,mixed> $journeyBasic
     */
    private function inferJourneyStructure(array $segments, array $journeyBasic, string $transportMode, array $ticketExtracts = []): string
    {
        if (count($segments) > 1) {
            $segmentModes = $this->extractModesFromSegments($segments, $transportMode);

            return count($segmentModes) > 1
                ? 'multimodal_connections'
                : 'single_mode_connections';
        }

        if (count($ticketExtracts) > 1) {
            $ticketModes = [];
            foreach ($ticketExtracts as $extract) {
                if (!is_array($extract)) {
                    continue;
                }
                foreach ((array)($extract['modes'] ?? []) as $mode) {
                    $mode = strtolower(trim((string)$mode));
                    if (in_array($mode, ['rail', 'ferry', 'bus', 'air'], true)) {
                        $ticketModes[$mode] = true;
                    }
                }
            }
            if ($ticketModes !== []) {
                return count($ticketModes) > 1
                    ? 'multimodal_connections'
                    : 'single_mode_connections';
            }
        }

        if (!empty($journeyBasic['dep_station']) || !empty($journeyBasic['arr_station'])) {
            return 'single_segment';
        }

        return 'unknown';
    }

    private function detectSelfTransferFromText(string $text): bool
    {
        $text = strtolower(trim($text));
        if ($text === '') {
            return false;
        }

        return (bool)preg_match('/self[- ]transfer|separate tickets|independent segments|not protected connection/', $text);
    }

    private function detectProtectedConnectionFromText(string $text): bool
    {
        $text = strtolower(trim($text));
        if ($text === '') {
            return false;
        }

        return (bool)preg_match('/protected connection|through ticket|through itinerary|operated in cooperation|connection protected/', $text);
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildScopeMeta(array $flow, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);

        return [
            'transport_mode' => $transportMode,
            'service_type' => $form['service_type'] ?? null,
            'departure_from_terminal' => $this->normalizeNullableBool($form['departure_from_terminal'] ?? null),
            'departure_port_in_eu' => $this->normalizeNullableBool($form['departure_port_in_eu'] ?? null),
            'arrival_port_in_eu' => $this->normalizeNullableBool($form['arrival_port_in_eu'] ?? null),
            'carrier_is_eu' => $this->normalizeNullableBool($form['carrier_is_eu'] ?? null),
            'departure_airport_in_eu' => $this->normalizeNullableBool($form['departure_airport_in_eu'] ?? null),
            'arrival_airport_in_eu' => $this->normalizeNullableBool($form['arrival_airport_in_eu'] ?? null),
            'operating_carrier_is_eu' => $this->normalizeNullableBool($form['operating_carrier_is_eu'] ?? null),
            'marketing_carrier_is_eu' => $this->normalizeNullableBool($form['marketing_carrier_is_eu'] ?? null),
            'flight_distance_km' => $this->normalizeNullableInt($form['flight_distance_km'] ?? null),
            'air_distance_band' => $form['air_distance_band'] ?? null,
            'air_delay_threshold_hours' => $this->normalizeNullableInt($form['air_delay_threshold_hours'] ?? null),
            'intra_eu_over_1500' => $this->normalizeNullableBool($form['intra_eu_over_1500'] ?? null),
            'bus_regular_service' => $this->normalizeNullableBool($form['bus_regular_service'] ?? null),
            'boarding_in_eu' => $this->normalizeNullableBool($form['boarding_in_eu'] ?? null),
            'alighting_in_eu' => $this->normalizeNullableBool($form['alighting_in_eu'] ?? null),
            'scheduled_distance_km' => $this->normalizeNullableInt($form['scheduled_distance_km'] ?? null),
            'vessel_passenger_capacity' => $this->normalizeNullableInt($form['vessel_passenger_capacity'] ?? null),
            'vessel_operational_crew' => $this->normalizeNullableInt($form['vessel_operational_crew'] ?? null),
            'route_distance_meters' => $this->normalizeNullableInt($form['route_distance_meters'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildIncidentMeta(array $flow, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $incident = (array)($flow['incident'] ?? []);
        $overnightRequired = $form['overnight_required'] ?? null;
        if ($transportMode === 'ferry') {
            $overnightRequired = $form['ferry_overnight_required']
                ?? ($form['overnight_needed'] ?? ($form['overnight_required'] ?? null));
        }
        $passengerFault = $transportMode === 'ferry'
            ? null
            : $this->normalizeNullableBool($form['passenger_fault'] ?? null);

        $incidentType = strtolower(trim((string)($incident['main'] ?? ($form['incident_main'] ?? ''))));
        if (!in_array($incidentType, ['delay', 'cancellation', 'denied_boarding', 'missed_connection'], true)) {
            $incidentType = $incidentType !== '' ? $incidentType : null;
        }

        return [
            'transport_mode' => $transportMode,
            'gating_mode' => $this->normalizeIncidentMode($form['gating_mode'] ?? ($flow['flags']['gating_mode'] ?? null), $transportMode),
            'initial_incident_mode' => $this->normalizeIncidentMode($form['initial_incident_mode'] ?? ($flow['flags']['initial_incident_mode'] ?? null), $transportMode),
            'primary_incident_mode' => $this->normalizeIncidentMode($form['primary_incident_mode'] ?? ($form['gating_mode'] ?? null), $transportMode),
            'primary_incident_type' => strtolower(trim((string)($form['primary_incident_type'] ?? ($incidentType ?? '')))),
            'incident_type' => $incidentType,
            'incident_missed' => $this->normalizeYesNoMachine($incident['missed'] ?? ($form['incident_missed'] ?? null)),
            'expected_delay_60' => $this->normalizeNullableBool($form['expected_delay_60'] ?? null),
            'delay_already_60' => $this->normalizeNullableBool($form['delay_already_60'] ?? null),
            'missed_expected_delay_60' => $this->normalizeNullableBool($form['missed_expected_delay_60'] ?? null),
            'national_delay_minutes' => $this->normalizeNullableInt($form['national_delay_minutes'] ?? null),
            'preinformed_disruption' => $this->normalizeNullableBool($form['preinformed_disruption'] ?? null),
            'preinfo_channel' => $form['preinfo_channel'] ?? null,
            'realtime_info_seen' => $form['realtime_info_seen'] ?? null,
            'operator_exceptional_circumstances' => $this->normalizeNullableBool($form['operatorExceptionalCircumstances'] ?? null),
            'operator_exceptional_type' => $form['operatorExceptionalType'] ?? null,
            'minimum_threshold_applies' => $this->normalizeNullableBool($form['minThresholdApplies'] ?? null),
            'arrival_delay_minutes' => $this->normalizeNullableInt($form['arrival_delay_minutes'] ?? null),
            'scheduled_journey_duration_minutes' => $this->normalizeNullableInt($form['scheduled_journey_duration_minutes'] ?? null),
            'expected_departure_delay_90' => $this->normalizeNullableBool($form['expected_departure_delay_90'] ?? null),
            'actual_departure_delay_90' => $this->normalizeNullableBool($form['actual_departure_delay_90'] ?? null),
            'overnight_required' => $this->normalizeNullableBool($overnightRequired),
            'informed_before_purchase' => $this->normalizeNullableBool($form['informed_before_purchase'] ?? null),
            'passenger_fault' => $passengerFault,
            'weather_safety' => $this->normalizeNullableBool($form['weather_safety'] ?? null),
            'extraordinary_circumstances' => $this->normalizeNullableBool($form['extraordinary_circumstances'] ?? null),
            'open_ticket_without_departure_time' => $this->normalizeNullableBool($form['open_ticket_without_departure_time'] ?? null),
            'season_ticket' => array_key_exists('season_ticket', $form)
                ? $this->normalizeNullableBool($form['season_ticket'])
                : (($form['ticket_upload_mode'] ?? null) === 'seasonpass'),
            'delay_departure_band' => $form['delay_departure_band'] ?? null,
            'planned_duration_band' => $form['planned_duration_band'] ?? null,
            'delay_minutes_departure' => $this->normalizeNullableInt($form['delay_minutes_departure'] ?? null),
            'delay_minutes_arrival' => $this->normalizeNullableInt($form['delay_minutes_arrival'] ?? null),
            'boarding_denied' => $this->normalizeNullableBool($form['boarding_denied'] ?? null),
            'voluntary_denied_boarding' => $this->normalizeNullableBool($form['voluntary_denied_boarding'] ?? null),
            'reroute_offered' => $this->normalizeNullableBool($form['reroute_offered'] ?? null),
            'cancellation_notice_band' => $form['cancellation_notice_band'] ?? null,
            'reroute_departure_band' => $form['reroute_departure_band'] ?? null,
            'reroute_arrival_band' => $form['reroute_arrival_band'] ?? null,
            'refund_offered' => $this->normalizeNullableBool($form['refund_offered'] ?? null),
            'hotel_required' => $this->normalizeNullableBool($form['hotel_required'] ?? null),
            'hotel_offered' => $this->normalizeNullableBool($form['hotel_offered'] ?? null),
            'meal_offered' => $this->normalizeNullableBool($form['meal_offered'] ?? null),
            'pmr_user' => $this->normalizeNullableBool($form['pmr_user'] ?? null),
            'pmr_companion' => $this->normalizeNullableBool($form['pmr_companion'] ?? null),
            'pmr_service_dog' => $this->normalizeNullableBool($form['pmr_service_dog'] ?? null),
            'unaccompanied_minor' => $this->normalizeNullableBool($form['unaccompanied_minor'] ?? null),
            'ferry_pmr_companion' => $this->normalizeNullableBool($form['ferry_pmr_companion'] ?? null),
            'ferry_pmr_service_dog' => $this->normalizeNullableBool($form['ferry_pmr_service_dog'] ?? null),
            'ferry_pmr_notice_48h' => $this->normalizeNullableBool($form['ferry_pmr_notice_48h'] ?? null),
            'ferry_pmr_met_checkin_time' => $this->normalizeNullableBool($form['ferry_pmr_met_checkin_time'] ?? null),
            'ferry_pmr_assistance_delivered' => $this->normalizeAllowedString($form['ferry_pmr_assistance_delivered'] ?? null, ['full', 'partial', 'none', 'unknown']),
            'ferry_pmr_boarding_refused' => $this->normalizeNullableBool($form['ferry_pmr_boarding_refused'] ?? null),
            'ferry_pmr_refusal_basis' => $this->normalizeAllowedString($form['ferry_pmr_refusal_basis'] ?? null, ['safety_requirements', 'port_or_ship_infrastructure', 'other_or_unknown']),
            'ferry_pmr_reason_given' => $this->normalizeNullableBool($form['ferry_pmr_reason_given'] ?? null),
            'ferry_pmr_alternative_transport_offered' => $this->normalizeNullableBool($form['ferry_pmr_alternative_transport_offered'] ?? null),
            'bus_pmr_companion' => $this->normalizeNullableBool($form['bus_pmr_companion'] ?? null),
            'bus_pmr_notice_36h' => $this->normalizeNullableBool($form['bus_pmr_notice_36h'] ?? null),
            'bus_pmr_met_terminal_time' => $this->normalizeNullableBool($form['bus_pmr_met_terminal_time'] ?? null),
            'bus_pmr_special_seating_notified' => $this->normalizeNullableBool($form['bus_pmr_special_seating_notified'] ?? null),
            'bus_pmr_assistance_delivered' => $this->normalizeAllowedString($form['bus_pmr_assistance_delivered'] ?? null, ['full', 'partial', 'none', 'unknown']),
            'bus_pmr_boarding_refused' => $this->normalizeNullableBool($form['bus_pmr_boarding_refused'] ?? null),
            'bus_pmr_refusal_basis' => $this->normalizeAllowedString($form['bus_pmr_refusal_basis'] ?? null, ['safety_requirements', 'impossible_infrastructure', 'other_or_unknown']),
            'bus_pmr_reason_given' => $this->normalizeNullableBool($form['bus_pmr_reason_given'] ?? null),
            'bus_pmr_alternative_transport_offered' => $this->normalizeNullableBool($form['bus_pmr_alternative_transport_offered'] ?? null),
            'protected_connection_missed' => $this->normalizeNullableBool($form['protected_connection_missed'] ?? null),
            'reroute_arrival_delay_minutes' => $this->normalizeNullableInt($form['reroute_arrival_delay_minutes'] ?? null),
            'overbooking' => $this->normalizeNullableBool($form['overbooking'] ?? null),
            'carrier_offered_choice' => $this->normalizeNullableBool($form['carrier_offered_choice'] ?? null),
            'severe_weather' => $this->normalizeNullableBool($form['severe_weather'] ?? null),
            'major_natural_disaster' => $this->normalizeNullableBool($form['major_natural_disaster'] ?? null),
            'missed_connection_due_to_delay' => $this->normalizeNullableBool($form['missed_connection_due_to_delay'] ?? null),
            'follow_on_missed_connection' => $this->normalizeNullableBool($form['follow_on_missed_connection'] ?? null),
            'next_segment_operated_normally' => $this->normalizeNullableBool($form['next_segment_operated_normally'] ?? null),
            'incident_chain' => (array)($flow['meta']['incident_chain'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @param array<string,mixed> $ticketEvidence
     * @param array<string,mixed> $contractMeta
     * @param array<string,mixed> $scopeMeta
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $contractResult
     * @param array<string,mixed> $rightsResult
     * @param array<string,mixed> $claimDirection
     * @param array<string,mixed> $contractDecision
     * @return array<string,mixed>
     */
    private function buildCanonicalModel(
        array $flow,
        string $transportMode,
        array $journeyBasic,
        array $segments,
        array $ticketEvidence,
        array $contractMeta,
        array $scopeMeta,
        array $incidentMeta,
        array $scopeResult,
        array $contractResult,
        array $rightsResult,
        array $claimDirection,
        array $contractDecision
    ): array {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $canonicalJourneyId = $this->buildCanonicalJourneyId($transportMode, $journeyBasic, $segments);
        $legs = $this->buildCanonicalLegs($transportMode, $journeyBasic, $segments, $form);
        $incident = $this->buildCanonicalIncident($transportMode, $incidentMeta, $legs, $form, $meta);

        return [
            'version' => 1,
            'journey_id' => $canonicalJourneyId,
            'transport_mode' => $transportMode,
            'journey' => [
                'journey_id' => $canonicalJourneyId,
                'transport_mode' => $transportMode,
                'operator' => $journeyBasic['operator'] ?? null,
                'operator_country' => $journeyBasic['operator_country'] ?? null,
                'product' => $journeyBasic['operator_product'] ?? null,
                'ticket_no' => $journeyBasic['ticket_no'] ?? null,
                'origin' => $journeyBasic['dep_station'] ?? null,
                'destination' => $journeyBasic['arr_station'] ?? null,
                'scheduled_distance_km' => $scopeMeta['scheduled_distance_km'] ?? null,
                'flight_distance_km' => $scopeMeta['flight_distance_km'] ?? null,
                'route_distance_meters' => $scopeMeta['route_distance_meters'] ?? null,
                'journey_structure' => $contractMeta['journey_structure'] ?? null,
                'ticket_upload_mode' => $form['ticket_upload_mode'] ?? null,
            ],
            'legs' => $legs,
            'incident' => $incident,
            'contract' => [
                'contract_topology' => $contractMeta['contract_topology'] ?? null,
                'contract_topology_confidence' => $contractMeta['contract_topology_confidence'] ?? null,
                'claim_transport_mode' => $contractMeta['claim_transport_mode'] ?? null,
                'original_contract_mode' => $contractMeta['original_contract_mode'] ?? null,
                'seller_type' => $contractMeta['seller_type'] ?? null,
                'seller_name' => $contractMeta['seller_name'] ?? null,
                'shared_booking_reference' => $contractMeta['shared_booking_reference'] ?? null,
                'single_transaction' => $contractMeta['single_transaction'] ?? null,
                'incident_segment_mode' => $contractMeta['incident_segment_mode'] ?? null,
                'incident_segment_operator' => $contractMeta['incident_segment_operator'] ?? null,
                'claim_direction' => $claimDirection,
                'decision' => $contractDecision,
            ],
            'legal_assessment' => $this->buildCanonicalLegalAssessment(
                $transportMode,
                $scopeMeta,
                $scopeResult,
                $incidentMeta,
                $rightsResult,
                $claimDirection
            ),
            'evidence' => $this->buildCanonicalEvidence($flow, $journeyBasic, $ticketEvidence, $legs),
            'raw' => [
                'scope_meta' => $scopeMeta,
                'incident_meta' => $incidentMeta,
                'rights' => $rightsResult,
                'scope' => $scopeResult,
                'contract' => $contractResult,
                'mode_classification' => (array)($meta['_mode_classification'] ?? []),
                'grouped_contract_count' => count((array)($ticketEvidence['grouped_contracts'] ?? [])),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     */
    private function buildCanonicalJourneyId(string $transportMode, array $journeyBasic, array $segments): string
    {
        $seed = [
            $transportMode,
            (string)($journeyBasic['operator'] ?? ''),
            (string)($journeyBasic['ticket_no'] ?? ''),
            (string)($journeyBasic['dep_station'] ?? ''),
            (string)($journeyBasic['arr_station'] ?? ''),
            (string)count($segments),
        ];

        return 'journey_' . substr(sha1(implode('|', $seed)), 0, 12);
    }

    /**
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @param array<string,mixed> $form
     * @return array<int,array<string,mixed>>
     */
    private function buildCanonicalLegs(string $transportMode, array $journeyBasic, array $segments, array $form): array
    {
        $legs = [];
        foreach (array_values($segments) as $index => $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentMode = strtolower(trim((string)($segment['mode'] ?? $transportMode)));
            if (!in_array($segmentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
                $segmentMode = $transportMode;
            }
            $legs[] = [
                'leg_id' => 'leg_' . ($index + 1),
                'mode' => $segmentMode,
                'operator' => $segment['carrier'] ?? ($segment['operator'] ?? ($journeyBasic['operator'] ?? null)),
                'origin' => $segment['from'] ?? ($segment['origin'] ?? null),
                'destination' => $segment['to'] ?? ($segment['destination'] ?? null),
                'planned_departure' => $this->combineDateTime($segment['depDate'] ?? null, $segment['schedDep'] ?? ($segment['planned_departure'] ?? null)),
                'planned_arrival' => $this->combineDateTime($segment['arrDate'] ?? ($segment['depDate'] ?? null), $segment['schedArr'] ?? ($segment['planned_arrival'] ?? null)),
                'actual_departure' => $this->combineDateTime($segment['actDepDate'] ?? ($segment['depDate'] ?? null), $segment['actDep'] ?? ($segment['actual_departure'] ?? null)),
                'actual_arrival' => $this->combineDateTime($segment['actArrDate'] ?? ($segment['arrDate'] ?? ($segment['depDate'] ?? null)), $segment['actArr'] ?? ($segment['actual_arrival'] ?? null)),
                'service_date' => $segment['depDate'] ?? null,
                'product' => $segment['product'] ?? null,
                'reference' => $segment['pnr'] ?? ($segment['ticket_no'] ?? null),
            ];
        }

        if ($legs !== []) {
            return $legs;
        }

        return [[
            'leg_id' => 'leg_1',
            'mode' => $transportMode,
            'operator' => $journeyBasic['operator'] ?? null,
            'origin' => $journeyBasic['dep_station'] ?? null,
            'destination' => $journeyBasic['arr_station'] ?? null,
            'planned_departure' => $this->combineDateTime($form['dep_date'] ?? null, $form['dep_time'] ?? null),
            'planned_arrival' => $this->combineDateTime($form['dep_date'] ?? null, $form['arr_time'] ?? null),
            'actual_departure' => $this->combineDateTime($form['actual_arrival_date'] ?? ($form['dep_date'] ?? null), $form['actual_dep_time'] ?? null),
            'actual_arrival' => $this->combineDateTime($form['actual_arrival_date'] ?? ($form['dep_date'] ?? null), $form['actual_arr_time'] ?? null),
            'service_date' => $form['dep_date'] ?? null,
            'product' => $journeyBasic['operator_product'] ?? null,
            'reference' => $journeyBasic['ticket_no'] ?? null,
        ]];
    }

    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<int,array<string,mixed>> $legs
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function buildCanonicalIncident(string $transportMode, array $incidentMeta, array $legs, array $form, array $meta): array
    {
        $incidentMode = strtolower(trim((string)($incidentMeta['gating_mode'] ?? $transportMode)));
        if (!in_array($incidentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            $incidentMode = $transportMode;
        }

        return [
            'incident_id' => 'incident_primary',
            'incident_type' => $incidentMeta['incident_type'] ?? null,
            'incident_mode' => $incidentMode,
            'incident_leg_id' => $this->resolveCanonicalIncidentLegId($incidentMode, $legs),
            'incident_missed' => $incidentMeta['incident_missed'] ?? null,
            'protected_connection_missed' => $incidentMeta['protected_connection_missed'] ?? null,
            'missed_connection_due_to_delay' => $incidentMeta['missed_connection_due_to_delay'] ?? null,
            'delay_departure_band' => $incidentMeta['delay_departure_band'] ?? null,
            'delay_minutes_departure' => $incidentMeta['delay_minutes_departure'] ?? null,
            'delay_minutes_arrival' => $incidentMeta['delay_minutes_arrival'] ?? null,
            'arrival_delay_minutes' => $incidentMeta['arrival_delay_minutes'] ?? null,
            'expected_departure_delay_90' => $incidentMeta['expected_departure_delay_90'] ?? null,
            'actual_departure_delay_90' => $incidentMeta['actual_departure_delay_90'] ?? null,
            'boarding_denied' => $incidentMeta['boarding_denied'] ?? null,
            'voluntary_denied_boarding' => $incidentMeta['voluntary_denied_boarding'] ?? null,
            'pmr_user' => $incidentMeta['pmr_user'] ?? null,
            'pmr_companion' => $incidentMeta['pmr_companion'] ?? null,
            'pmr_service_dog' => $incidentMeta['pmr_service_dog'] ?? null,
            'unaccompanied_minor' => $incidentMeta['unaccompanied_minor'] ?? null,
            'ferry_pmr_companion' => $incidentMeta['ferry_pmr_companion'] ?? null,
            'ferry_pmr_service_dog' => $incidentMeta['ferry_pmr_service_dog'] ?? null,
            'ferry_pmr_notice_48h' => $incidentMeta['ferry_pmr_notice_48h'] ?? null,
            'ferry_pmr_met_checkin_time' => $incidentMeta['ferry_pmr_met_checkin_time'] ?? null,
            'ferry_pmr_assistance_delivered' => $incidentMeta['ferry_pmr_assistance_delivered'] ?? null,
            'ferry_pmr_boarding_refused' => $incidentMeta['ferry_pmr_boarding_refused'] ?? null,
            'ferry_pmr_refusal_basis' => $incidentMeta['ferry_pmr_refusal_basis'] ?? null,
            'ferry_pmr_reason_given' => $incidentMeta['ferry_pmr_reason_given'] ?? null,
            'ferry_pmr_alternative_transport_offered' => $incidentMeta['ferry_pmr_alternative_transport_offered'] ?? null,
            'bus_pmr_companion' => $incidentMeta['bus_pmr_companion'] ?? null,
            'bus_pmr_notice_36h' => $incidentMeta['bus_pmr_notice_36h'] ?? null,
            'bus_pmr_met_terminal_time' => $incidentMeta['bus_pmr_met_terminal_time'] ?? null,
            'bus_pmr_special_seating_notified' => $incidentMeta['bus_pmr_special_seating_notified'] ?? null,
            'bus_pmr_assistance_delivered' => $incidentMeta['bus_pmr_assistance_delivered'] ?? null,
            'bus_pmr_boarding_refused' => $incidentMeta['bus_pmr_boarding_refused'] ?? null,
            'bus_pmr_refusal_basis' => $incidentMeta['bus_pmr_refusal_basis'] ?? null,
            'bus_pmr_reason_given' => $incidentMeta['bus_pmr_reason_given'] ?? null,
            'bus_pmr_alternative_transport_offered' => $incidentMeta['bus_pmr_alternative_transport_offered'] ?? null,
            'reroute_offered' => $incidentMeta['reroute_offered'] ?? null,
            'cancellation_notice_band' => $incidentMeta['cancellation_notice_band'] ?? null,
            'extraordinary_circumstances' => $incidentMeta['extraordinary_circumstances'] ?? null,
            'weather_safety' => $incidentMeta['weather_safety'] ?? null,
            'operator_exceptional_circumstances' => $incidentMeta['operator_exceptional_circumstances'] ?? null,
            'incident_chain' => (array)($incidentMeta['incident_chain'] ?? ($meta['incident_chain'] ?? [])),
            'incident_segment_mode' => $form['incident_segment_mode'] ?? null,
            'incident_segment_operator' => $form['incident_segment_operator'] ?? null,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     */
    private function resolveCanonicalIncidentLegId(string $incidentMode, array $legs): ?string
    {
        foreach ($legs as $leg) {
            $legMode = strtolower(trim((string)($leg['mode'] ?? '')));
            if ($legMode === $incidentMode) {
                return (string)($leg['leg_id'] ?? 'leg_1');
            }
        }

        return $legs !== [] ? (string)($legs[0]['leg_id'] ?? 'leg_1') : null;
    }

    /**
     * @param array<string,mixed> $scopeMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $rightsResult
     * @param array<string,mixed> $claimDirection
     * @return array<string,mixed>
     */
    private function buildCanonicalLegalAssessment(
        string $transportMode,
        array $scopeMeta,
        array $scopeResult,
        array $incidentMeta,
        array $rightsResult,
        array $claimDirection
    ): array {
        $genericGates = [
            'remedy' => false,
            'assistance' => false,
            'compensation' => false,
            'information' => false,
        ];
        $articleFlags = [];

        if ($transportMode === 'ferry') {
            $genericGates['remedy'] = !empty($rightsResult['gate_art18']);
            $genericGates['assistance'] = !empty($rightsResult['gate_art17_refreshments']) || !empty($rightsResult['gate_art17_hotel']);
            $genericGates['compensation'] = !empty($rightsResult['gate_art19']);
            $genericGates['information'] = !empty($rightsResult['gate_art16_notice']) || !empty($rightsResult['gate_art16_alt_connections']);
            $articleFlags = [
                'art16_notice' => !empty($rightsResult['gate_art16_notice']),
                'art16_alt_connections' => !empty($rightsResult['gate_art16_alt_connections']),
                'art17_refreshments' => !empty($rightsResult['gate_art17_refreshments']),
                'art17_hotel' => !empty($rightsResult['gate_art17_hotel']),
                'art18' => !empty($rightsResult['gate_art18']),
                'art19' => !empty($rightsResult['gate_art19']),
                'pmr_assistance' => !empty($rightsResult['gate_ferry_pmr_assistance']),
                'pmr_assistance_partial' => !empty($rightsResult['gate_ferry_pmr_assistance_partial']),
                'pmr_boarding_remedy' => !empty($rightsResult['gate_ferry_pmr_boarding_remedy']),
                'pmr_reason_notice' => !empty($rightsResult['gate_ferry_pmr_reason_notice']),
            ];
        } elseif ($transportMode === 'bus') {
            $genericGates['remedy'] = !empty($rightsResult['gate_bus_reroute_refund']) || !empty($rightsResult['gate_bus_pmr_boarding_remedy']);
            $genericGates['assistance'] = !empty($rightsResult['gate_bus_assistance_refreshments']) || !empty($rightsResult['gate_bus_assistance_hotel']) || !empty($rightsResult['gate_bus_pmr_assistance']) || !empty($rightsResult['gate_bus_pmr_assistance_partial']);
            $genericGates['compensation'] = !empty($rightsResult['gate_bus_compensation_50']);
            $genericGates['information'] = !empty($rightsResult['gate_bus_info']);
            $articleFlags = [
                'art19' => !empty($rightsResult['gate_bus_reroute_refund']),
                'art20' => !empty($rightsResult['gate_bus_info']),
                'art21_refreshments' => !empty($rightsResult['gate_bus_assistance_refreshments']),
                'art21_hotel' => !empty($rightsResult['gate_bus_assistance_hotel']),
                'art19_compensation_50' => !empty($rightsResult['gate_bus_compensation_50']),
                'pmr_assistance' => !empty($rightsResult['gate_bus_pmr_assistance']),
                'pmr_assistance_partial' => !empty($rightsResult['gate_bus_pmr_assistance_partial']),
                'pmr_boarding_remedy' => !empty($rightsResult['gate_bus_pmr_boarding_remedy']),
                'pmr_reason_notice' => !empty($rightsResult['gate_bus_pmr_reason_notice']),
            ];
        } elseif ($transportMode === 'air') {
            $genericGates['remedy'] = !empty($rightsResult['gate_air_reroute_refund']) || !empty($rightsResult['gate_air_delay_refund_5h']);
            $genericGates['assistance'] = !empty($rightsResult['gate_air_care']);
            $genericGates['compensation'] = !empty($rightsResult['gate_air_compensation']);
            $genericGates['information'] = false;
            $articleFlags = [
                'art8' => $genericGates['remedy'],
                'art9' => $genericGates['assistance'],
                'art7' => $genericGates['compensation'],
                'delay_refund_5h' => !empty($rightsResult['gate_air_delay_refund_5h']),
                'denied_boarding' => !empty($rightsResult['gate_air_denied_boarding']),
            ];
        }

        return [
            'transport_mode' => $transportMode,
            'regulation_scope_applies' => array_key_exists('regulation_applies', $scopeResult) ? (bool)$scopeResult['regulation_applies'] : null,
            'scope_exclusion_reason' => $scopeResult['scope_exclusion_reason'] ?? null,
            'rights_module' => $claimDirection['rights_module'] ?? $transportMode,
            'claim_party_type' => $claimDirection['claim_party_type'] ?? null,
            'claim_party_name' => $claimDirection['claim_party_name'] ?? null,
            'incident_type' => $incidentMeta['incident_type'] ?? null,
            'generic_gates' => $genericGates,
            'article_flags' => $articleFlags,
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $journeyBasic
     * @param array<string,mixed> $ticketEvidence
     * @param array<int,array<string,mixed>> $legs
     * @return array<int,array<string,mixed>>
     */
    private function buildCanonicalEvidence(array $flow, array $journeyBasic, array $ticketEvidence, array $legs): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $evidence = [];

        if (!empty($journeyBasic['ticket_no'])) {
            $evidence[] = [
                'type' => 'booking_reference',
                'source' => 'form_or_ticket',
                'value' => (string)$journeyBasic['ticket_no'],
            ];
        }
        if (!empty($form['_ticketFilename'])) {
            $evidence[] = [
                'type' => 'ticket_file',
                'source' => 'upload',
                'value' => basename((string)$form['_ticketFilename']),
            ];
        }
        if (!empty($ticketEvidence['ticket_extracts'])) {
            $evidence[] = [
                'type' => 'ticket_extracts',
                'source' => 'ocr_or_llm',
                'value' => count((array)$ticketEvidence['ticket_extracts']),
            ];
        }
        if (!empty($meta['_ocr_text'])) {
            $evidence[] = [
                'type' => 'ocr_text_present',
                'source' => 'ocr',
                'value' => true,
            ];
        }
        if ($legs !== []) {
            $evidence[] = [
                'type' => 'resolved_legs',
                'source' => 'normalization',
                'value' => count($legs),
            ];
        }

        return $evidence;
    }

    private function combineDateTime(mixed $date, mixed $time): ?string
    {
        $date = trim((string)$date);
        $time = trim((string)$time);
        if ($date === '' && $time === '') {
            return null;
        }
        if ($date !== '' && $time !== '') {
            return $date . 'T' . $time;
        }
        return $date !== '' ? $date : $time;
    }

    private function normalizeIncidentMode(mixed $value, string $fallbackMode): string
    {
        $mode = strtolower(trim((string)$value));
        return in_array($mode, ['rail', 'ferry', 'bus', 'air'], true) ? $mode : $fallbackMode;
    }

    private function normalizeAllowedString(mixed $value, array $allowed): ?string
    {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<int,array<string,mixed>> $segments
     */
    private function resolveOriginalContractMode(array $form, array $meta, array $segments, string $transportMode): ?string
    {
        foreach ([
            $form['original_contract_mode'] ?? null,
            $meta['original_contract_mode'] ?? null,
        ] as $candidate) {
            $normalized = strtolower(trim((string)$candidate));
            if (in_array($normalized, ['rail', 'ferry', 'bus', 'air'], true)) {
                return $normalized;
            }
        }

        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentMode = strtolower(trim((string)($segment['mode'] ?? '')));
            if (in_array($segmentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
                return $segmentMode;
            }
        }

        return in_array($transportMode, ['rail', 'ferry', 'bus', 'air'], true) ? $transportMode : null;
    }

    private function normalizeYesNoMachine(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            'ja', 'yes', 'y', '1', 'true', 'gennemgående' => 'yes',
            'nej', 'no', 'n', '0', 'false', 'særskilte' => 'no',
            '', 'unknown', 'ved ikke', 'unclear' => null,
            default => in_array($normalized, ['yes', 'no'], true) ? $normalized : null,
        };
    }

    private function normalizeDisclosure(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            'gennemgående', 'bundled' => 'bundled',
            'særskilte', 'separate' => 'separate',
            'yes', 'ja' => 'bundled',
            'no', 'nej' => 'none',
            '', 'unknown', 'ved ikke' => 'unknown',
            default => 'unknown',
        };
    }

    private function normalizeSeparateNotice(mixed $value): string
    {
        return match ($this->normalizeYesNoMachine($value)) {
            'yes' => 'yes',
            'no' => 'no',
            default => 'unclear',
        };
    }

    private function normalizeNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match ($this->normalizeYesNoMachine($value)) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $contractMeta
     * @param array<string,mixed> $contractResult
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $rightsResult
     * @return array<string,mixed>
     */
    private function buildClaimDirection(
        string $transportMode,
        array $contractMeta,
        array $contractResult,
        array $scopeResult,
        array $rightsResult
    ): array {
        $claimPartyType = (string)($contractResult['primary_claim_party'] ?? ($contractMeta['primary_claim_party'] ?? 'manual_review'));
        $claimPartyName = (string)($contractResult['primary_claim_party_name'] ?? ($contractMeta['seller_name'] ?? ''));
        $rightsModule = (string)($contractResult['rights_module'] ?? ($contractMeta['rights_module'] ?? $transportMode));
        $manualReview = (bool)($contractResult['manual_review_required'] ?? ($contractMeta['manual_review_required'] ?? false));

        $requiredDocuments = ['ticket_or_booking'];
        if ($transportMode === 'ferry') {
            if (!empty($rightsResult['gate_art17_refreshments'])) {
                $requiredDocuments[] = 'refreshment_receipts_if_self_paid';
            }
            if (!empty($rightsResult['gate_art17_hotel'])) {
                $requiredDocuments[] = 'hotel_receipts_if_self_paid';
            }
            if (!empty($rightsResult['gate_art18'])) {
                $requiredDocuments[] = 'reroute_or_refund_evidence';
            }
            if (!empty($rightsResult['gate_art19'])) {
                $requiredDocuments[] = 'arrival_delay_evidence';
            }
            if (!empty($rightsResult['gate_art16_notice']) || !empty($rightsResult['gate_art16_alt_connections'])) {
                $requiredDocuments[] = 'operator_information_evidence';
            }
        } elseif ($transportMode === 'bus') {
            $requiredDocuments[] = 'operator_connection_or_terminal_evidence';
            if (!empty($rightsResult['gate_bus_assistance_refreshments'])) {
                $requiredDocuments[] = 'refreshment_receipts_if_self_paid';
            }
            if (!empty($rightsResult['gate_bus_assistance_hotel'])) {
                $requiredDocuments[] = 'hotel_receipts_if_self_paid';
            }
            if (!empty($rightsResult['gate_bus_reroute_refund'])) {
                $requiredDocuments[] = 'reroute_or_refund_evidence';
            }
            if (!empty($rightsResult['gate_bus_compensation_50'])) {
                $requiredDocuments[] = 'operator_failed_to_offer_choice_evidence';
            }
            if (!empty($rightsResult['gate_bus_pmr_assistance']) || !empty($rightsResult['gate_bus_pmr_assistance_partial'])) {
                $requiredDocuments[] = 'pmr_assistance_request_or_terminal_evidence';
            }
            if (!empty($rightsResult['gate_bus_pmr_boarding_remedy'])) {
                $requiredDocuments[] = 'pmr_boarding_refusal_evidence';
            }
        } elseif ($transportMode === 'air') {
            $requiredDocuments[] = 'boarding_pass_or_pnr';
            if (!empty($rightsResult['gate_air_denied_boarding'])) {
                $requiredDocuments[] = 'denied_boarding_evidence';
            }
            if (!empty($rightsResult['gate_air_reroute_refund'])) {
                $requiredDocuments[] = 'reroute_or_refund_evidence';
            }
            if (!empty($rightsResult['gate_air_compensation'])) {
                $requiredDocuments[] = 'arrival_delay_or_cancellation_evidence';
            }
        }

        return [
            'transport_mode' => $transportMode,
            'contract_topology' => (string)($contractMeta['contract_topology'] ?? 'unknown_manual_review'),
            'claim_transport_mode' => (string)($contractMeta['claim_transport_mode'] ?? ($contractMeta['original_contract_mode'] ?? $transportMode)),
            'original_contract_mode' => (string)($contractMeta['original_contract_mode'] ?? $transportMode),
            'claim_party_type' => $claimPartyType,
            'claim_party_name' => $claimPartyName !== '' ? $claimPartyName : null,
            'rights_module' => $rightsModule,
            'contract_stop' => (string)($contractMeta['contract_topology'] ?? '') !== 'unknown_manual_review',
            'ticket_scope' => match ((string)($contractMeta['contract_topology'] ?? '')) {
                'separate_contracts' => 'separate',
                'protected_single_contract', 'single_multimodal_contract' => 'through',
                'single_mode_single_contract' => 'single',
                default => null,
            },
            'manual_review_required' => $manualReview,
            'scope_applies' => array_key_exists('regulation_applies', $scopeResult) ? (bool)$scopeResult['regulation_applies'] : null,
            'scope_exclusion_reason' => (string)($scopeResult['scope_exclusion_reason'] ?? ''),
            'recommended_documents' => array_values(array_unique($requiredDocuments)),
        ];
    }

    /**
     * @param array<string,mixed> $contractMeta
     * @param array<string,mixed> $contractResult
     * @param array<string,mixed> $scopeResult
     * @return array<string,mixed>
     */
    private function buildContractDecision(
        string $transportMode,
        array $contractMeta,
        array $contractResult,
        array $scopeResult
    ): array {
        $topology = (string)($contractMeta['contract_topology'] ?? 'unknown_manual_review');
        $claimPartyName = (string)($contractResult['primary_claim_party_name'] ?? ($contractMeta['seller_name'] ?? ''));
        $rightsModule = (string)($contractResult['rights_module'] ?? ($contractMeta['rights_module'] ?? $transportMode));
        $scopeApplies = array_key_exists('regulation_applies', $scopeResult) ? (bool)$scopeResult['regulation_applies'] : null;

        $decision = [
            'stage' => 'COLLECT',
            'ticket_scope' => null,
            'contract_label' => 'Kræver flere svar',
            'basis' => 'manual_review',
            'notes' => ['Kontraktstrukturen er ikke afgjort endnu.'],
            'booking_cohesion' => (string)($contractMeta['booking_cohesion'] ?? 'unknown'),
            'service_cohesion' => (string)($contractMeta['service_cohesion'] ?? 'unknown'),
            'contract_topology_confidence' => (string)($contractMeta['contract_topology_confidence'] ?? 'low'),
            'contract_topology_hint' => $contractMeta['contract_topology_hint'] ?? null,
            'manual_review_reasons' => array_values(array_map('strval', (array)($contractMeta['manual_review_reasons'] ?? []))),
            'claim_transport_mode' => (string)($contractMeta['claim_transport_mode'] ?? ($contractMeta['original_contract_mode'] ?? $transportMode)),
            'original_contract_mode' => (string)($contractMeta['original_contract_mode'] ?? $transportMode),
            'claim_party_name' => $claimPartyName !== '' ? $claimPartyName : null,
            'rights_module' => $rightsModule,
            'scope_applies' => $scopeApplies,
        ];

        switch ($topology) {
            case 'separate_contracts':
                $decision['stage'] = 'STOP';
                $decision['ticket_scope'] = 'separate';
                $decision['contract_label'] = 'Særskilte kontrakter';
                $decision['basis'] = 'separate_contracts';
                $decision['notes'] = ['Ansvar og kompensationsspor følger det ramte segment.'];
                break;

            case 'single_mode_single_contract':
                $decision['stage'] = 'STOP';
                $decision['ticket_scope'] = 'single';
                $decision['contract_label'] = 'Samlet kontrakt';
                $decision['basis'] = 'single_mode_single_contract';
                $decision['notes'] = ['Rejsen behandles som én samlet kontrakt for den valgte transportform.'];
                break;

            case 'protected_single_contract':
                $decision['stage'] = 'STOP';
                $decision['ticket_scope'] = 'through';
                $decision['contract_label'] = 'Beskyttet samlet booking';
                $decision['basis'] = 'protected_single_contract';
                $decision['notes'] = ['Forbindelsen behandles som en beskyttet samlet kontrakt.'];
                break;

            case 'single_multimodal_contract':
                $decision['stage'] = 'STOP';
                $decision['ticket_scope'] = 'through';
                $decision['contract_label'] = 'Samlet multimodal kontrakt';
                $decision['basis'] = 'single_multimodal_contract';
                $decision['notes'] = ['Claim-kanalen følger den samlede kontrakt, mens rettighedsmodulet følger det ramte segment.'];
                break;
        }

        if ($topology === 'unknown_manual_review' && !empty($contractMeta['contract_topology_hint'])) {
            $decision['notes'][] = 'ForelÃ¸bigt estimat: ' . (string)$contractMeta['contract_topology_hint'];
        }
        if (!empty($contractMeta['booking_cohesion'])) {
            $decision['notes'][] = 'Booking cohesion: ' . (string)$contractMeta['booking_cohesion'];
        }
        if (!empty($contractMeta['service_cohesion'])) {
            $decision['notes'][] = 'Service cohesion: ' . (string)$contractMeta['service_cohesion'];
        }

        return $decision;
    }
}
