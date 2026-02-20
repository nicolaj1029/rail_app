import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/case_close/data/evaluation_service.dart';
import 'package:mobile/features/case_close/data/receipt_service.dart';
import 'package:mobile/features/journeys/data/journeys_service.dart';
import 'package:mobile/shared/services/tickets_service.dart';

class CaseCloseScreen extends StatefulWidget {
  final Map<String, dynamic> journey;
  const CaseCloseScreen({super.key, required this.journey});

  @override
  State<CaseCloseScreen> createState() => _CaseCloseScreenState();
}

class _CaseCloseScreenState extends State<CaseCloseScreen> {
  int currentStep = 0;
  bool submitting = false;

  // Step 1-2: journey and incident basics
  String? travelStatus;
  String? eventType;
  bool missedConnection = false;
  final TextEditingController depCtrl = TextEditingController();
  final TextEditingController arrCtrl = TextEditingController();
  final TextEditingController depTimeCtrl = TextEditingController();
  final TextEditingController arrTimeCtrl = TextEditingController();
  final TextEditingController ticketTypeCtrl = TextEditingController();
  final TextEditingController delayCtrl = TextEditingController();
  final TextEditingController expectedDelayCtrl = TextEditingController();
  final TextEditingController connectingStationCtrl = TextEditingController();

  // Step 3: PMR / bike
  bool pmr = false;
  bool pmrPrebooked = false;
  bool withBike = false;

  // Step 6: tickets and uploads
  final List<_TicketItem> tickets = [_TicketItem()];
  int ticketUploadTargetIndex = 0;
  bool uploadingTicket = false;
  String? ticketUploadInfo;
  final ImagePicker _picker = ImagePicker();
  late final TicketsService ticketsService;
  late final EvaluationService evaluationService;
  final ReceiptService receiptService = ReceiptService();

  // Step 5: assistance flags
  bool skipAssistance = false;
  bool skipReceipts = false;
  bool needsWheelchair = false;
  bool needsEscort = false;
  final TextEditingController otherNeedsCtrl = TextEditingController();

  // Evaluation + submission state
  bool evaluating = false;
  String? evalError;
  Map<String, dynamic>? evalResult;
  String? submitError;
  String? submitSuccess;

  final List<_ReceiptItem> receipts = [];

  // Step 4 (Art.18 refund/reroute)
  bool tripCancelReturn = false;
  String refundRequested = 'unknown'; // yes/no/unknown
  String refundForm = '';
  bool rerouteSame = false;
  bool rerouteLater = false;
  String informedWithin100 = 'unknown'; // yes/no/unknown
  bool extraCosts = false;
  final TextEditingController extraCostAmountCtrl = TextEditingController();
  final TextEditingController extraCostCurrencyCtrl = TextEditingController(
    text: 'DKK',
  );
  bool downgradedDuringReroute = false;

  // Step 5 (assistance / Art.20)
  bool gotMeals = false;
  bool gotHotel = false;
  bool gotTransport = false;
  bool ownExpenses = false;
  bool delayConfirmation = false;
  String extraordinary = 'unknown'; // yes/no/unknown
  String extraordinaryType = '';

  // Representative
  bool representative = false;
  final TextEditingController repNameCtrl = TextEditingController();
  final TextEditingController repEmailCtrl = TextEditingController();
  final TextEditingController repPhoneCtrl = TextEditingController();
  final TextEditingController repRelationCtrl = TextEditingController();
  bool repConsent = false;

  // Force majeure dropdown
  String? forceMajeureReason;

  // Step 7 (compensation)
  String? compensationChoice;
  final TextEditingController refundAmountCtrl = TextEditingController();
  final TextEditingController refundCurrencyCtrl = TextEditingController(
    text: 'DKK',
  );

  @override
  void initState() {
    super.initState();
    final j = widget.journey;
    depCtrl.text = (j['dep_station'] ?? j['start'] ?? '').toString();
    arrCtrl.text = (j['arr_station'] ?? j['end'] ?? '').toString();
    depTimeCtrl.text = (j['dep_time'] ?? '').toString();
    arrTimeCtrl.text = (j['arr_time'] ?? '').toString();
    ticketTypeCtrl.text = (j['ticket_type'] ?? '').toString();
    ticketsService = TicketsService(baseUrl: apiBaseUrl);
    evaluationService = EvaluationService(baseUrl: apiBaseUrl);
  }

  @override
  void dispose() {
    depCtrl.dispose();
    arrCtrl.dispose();
    depTimeCtrl.dispose();
    arrTimeCtrl.dispose();
    ticketTypeCtrl.dispose();
    delayCtrl.dispose();
    expectedDelayCtrl.dispose();
    connectingStationCtrl.dispose();
    refundAmountCtrl.dispose();
    refundCurrencyCtrl.dispose();
    extraCostAmountCtrl.dispose();
    extraCostCurrencyCtrl.dispose();
    otherNeedsCtrl.dispose();
    repNameCtrl.dispose();
    repEmailCtrl.dispose();
    repPhoneCtrl.dispose();
    repRelationCtrl.dispose();
    for (final t in tickets) {
      t.dispose();
    }
    for (final r in receipts) {
      r.dispose();
    }
    super.dispose();
  }

  int _parsedDelayMinutes() {
    final raw = delayCtrl.text.trim();
    if (raw.isEmpty) return 0;
    return int.tryParse(raw) ?? 0;
  }

  int _parsedExpectedMinutes() {
    final raw = expectedDelayCtrl.text.trim();
    if (raw.isEmpty) return 0;
    return int.tryParse(raw) ?? 0;
  }

  int _effectiveDelay() {
    return travelStatus == 'completed'
        ? _parsedDelayMinutes()
        : _parsedExpectedMinutes();
  }

  StepState _stateFor(int step) {
    if (step < currentStep) return StepState.complete;
    if (step == currentStep) return StepState.editing;
    return StepState.indexed;
  }

  void _addReceipt() {
    setState(() => receipts.add(_ReceiptItem()));
  }

  void _removeReceipt(int index) {
    setState(() => receipts.removeAt(index));
  }

  void _addTicket() {
    setState(() {
      tickets.add(_TicketItem());
      ticketUploadTargetIndex = tickets.length - 1;
    });
  }

  void _removeTicket(int index) {
    if (tickets.length <= 1) return;
    setState(() {
      tickets.removeAt(index);
      ticketUploadTargetIndex = 0;
    });
  }

  void _applyTicketMatch(Map<String, dynamic> match) {
    if (tickets.isEmpty) tickets.add(_TicketItem());
    final targetIndex = ticketUploadTargetIndex.clamp(0, tickets.length - 1);
    final t = tickets[targetIndex];
    setState(() {
      t.numberCtrl.text =
          (match['pnr'] ?? match['ticket_number'] ?? t.numberCtrl.text)
              .toString();
      t.priceCtrl.text =
          (match['price'] ?? match['ticket_price'] ?? t.priceCtrl.text)
              .toString();
      t.currencyCtrl.text = (match['currency'] ?? t.currencyCtrl.text)
          .toString();
      t.typeCtrl.text = (match['ticket_type'] ?? t.typeCtrl.text).toString();
      t.throughTicket = match['through_ticket'] == true;
      depCtrl.text = (match['dep_station'] ?? depCtrl.text).toString();
      arrCtrl.text = (match['arr_station'] ?? arrCtrl.text).toString();
      depTimeCtrl.text = (match['dep_time'] ?? depTimeCtrl.text).toString();
      arrTimeCtrl.text = (match['arr_time'] ?? arrTimeCtrl.text).toString();
    });
  }

  Future<void> _uploadTicket(ImageSource source) async {
    if (tickets.isEmpty) {
      tickets.add(_TicketItem());
      ticketUploadTargetIndex = 0;
    }
    final targetIndex = ticketUploadTargetIndex.clamp(0, tickets.length - 1);
    final picked = await _picker.pickImage(source: source, imageQuality: 85);
    if (picked == null) return;
    final deviceId = widget.journey['device_id'] ?? widget.journey['deviceId'];
    if (deviceId == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Mangler device_id for upload.')),
        );
      }
      return;
    }
    setState(() {
      uploadingTicket = true;
      ticketUploadInfo = null;
    });
    try {
      final match = await ticketsService.matchTicket(
        deviceId: deviceId.toString(),
        journeyId: (widget.journey['id'] ?? '').toString(),
        filePath: picked.path,
      );
      _applyTicketMatch(match);
      setState(() => ticketUploadInfo = 'Billet #${targetIndex + 1} matchet');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Billet #${targetIndex + 1} uploadet og matchet'),
          ),
        );
      }
    } catch (e) {
      setState(() => ticketUploadInfo = 'Fejl: $e');
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Upload fejlede: $e')));
      }
    } finally {
      if (mounted) setState(() => uploadingTicket = false);
    }
  }

  void _next() {
    if (currentStep < 6) {
      setState(() => currentStep += 1);
    } else {
      _submit();
    }
  }

  void _back() {
    if (currentStep > 0) setState(() => currentStep -= 1);
  }

  void _submit() {
    if (submitting) return;
    final id = (widget.journey['id'] ?? '').toString();
    setState(() {
      submitting = true;
      submitError = null;
      submitSuccess = null;
    });
    final svc = JourneysService(baseUrl: apiBaseUrl);
    final payload = _buildPayload();
    svc
        .submit(id, payload)
        .then((res) {
          setState(() => submitSuccess = 'Indsendt: ${res['data'] ?? res}');
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Indsendt (stub)')));
        })
        .catchError((e) {
          setState(() => submitError = '$e');
        })
        .whenComplete(() => setState(() => submitting = false));
  }

  String _assistSummary() {
    final parts = <String>[];
    if (gotMeals) parts.add('mad');
    if (gotHotel) parts.add('hotel');
    if (gotTransport) parts.add('transport');
    if (needsWheelchair || needsEscort || otherNeedsCtrl.text.isNotEmpty) {
      parts.add('særlige behov');
    }
    return parts.isEmpty ? 'Ingen' : parts.join(', ');
  }

  Widget _summaryRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w500)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _previewJson() async {
    final payload = _buildPayload();
    final pretty = const JsonEncoder.withIndent('  ').convert(payload);
    if (!mounted) return;
    await showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Payload forhåndsvisning'),
        content: SizedBox(
          width: 600,
          height: 400,
          child: SingleChildScrollView(child: SelectableText(pretty)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Luk'),
          ),
        ],
      ),
    );
  }

  Future<void> _copyJson() async {
    final payload = _buildPayload();
    final pretty = const JsonEncoder.withIndent('  ').convert(payload);
    await Clipboard.setData(ClipboardData(text: pretty));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('JSON kopieret til udklipsholder')),
    );
  }

  Future<void> _evaluateOnServer() async {
    if (evaluating) return;
    setState(() {
      evaluating = true;
      evalError = null;
      evalResult = null;
    });
    try {
      final payload = _buildPayload();
      final res = await evaluationService.evaluateCaseClose(payload);
      if (!mounted) return;
      setState(() => evalResult = res);
      final comp = (res['compensation'] ?? {}) as Map<String, dynamic>;
      final amount = comp['amount'] ?? '-';
      final curr = comp['currency'] ?? '';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Evalueret: kompensation ${amount.toString()} ${curr.toString()}',
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => evalError = e.toString());
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Evaluering fejlede: $e')));
    } finally {
      if (!mounted) return;
      setState(() => evaluating = false);
    }
  }

  Map<String, dynamic> _buildPayload() {
    final delayConfirmed = _parsedDelayMinutes();
    final delayExpected = _parsedExpectedMinutes();
    final isCancellation = eventType == 'cancellation';
    final isMissed = missedConnection;
    final effectiveDelay = _effectiveDelay();
    final artEligible = isCancellation || isMissed || effectiveDelay >= 60;

    final journeyStatus = switch (travelStatus) {
      'completed' => 'completed',
      'in_progress' => 'ongoing',
      'not_started' => 'none',
      _ => 'unknown',
    };

    final ticketPayload = tickets
        .map(
          (t) => {
            'pnr': t.numberCtrl.text,
            'price': t.priceCtrl.text,
            'currency': t.currencyCtrl.text,
            'ticketType': t.typeCtrl.text,
            'throughTicket': t.throughTicket,
            'downgraded': t.downgraded,
            'from': t.depCtrl.text,
            'to': t.arrCtrl.text,
            'depTime': t.depTimeCtrl.text,
            'arrTime': t.arrTimeCtrl.text,
          },
        )
        .toList();

    final receiptsPayload = skipReceipts
        ? []
        : receipts
              .map(
                (r) => {
                  'category': r.category,
                  'type': r.typeCtrl.text,
                  'amount': r.amountCtrl.text,
                  'currency': r.currencyCtrl.text,
                  'date': r.dateCtrl.text,
                },
              )
              .toList();

    final representativePayload = representative
        ? {
            'isRepresentative': true,
            'name': repNameCtrl.text,
            'email': repEmailCtrl.text,
            'phone': repPhoneCtrl.text,
            'relationship': repRelationCtrl.text,
            'consent': repConsent,
          }
        : null;

    return {
      'journeyStatus': journeyStatus,
      'incident': {
        'delay_expected_minutes': delayExpected == 0 ? null : delayExpected,
        'delay_confirmed_minutes': delayConfirmed == 0 ? null : delayConfirmed,
        'cancellation': isCancellation,
        'missed_connection': isMissed,
        'connecting_station': connectingStationCtrl.text,
      },
      'art9': {'pmr': pmr, 'pmr_prebooked': pmrPrebooked, 'bicycle': withBike},
      'art18': {
        'refund_requested': refundRequested,
        'refund_form': refundForm,
        'reroute_same_conditions': rerouteSame,
        'reroute_later': rerouteLater,
        'trip_cancel_return': tripCancelReturn,
        'informed_within_100': informedWithin100,
        'extra_costs': extraCosts,
        'extra_cost_amount': extraCostAmountCtrl.text,
        'extra_cost_currency': extraCostCurrencyCtrl.text,
        'downgraded_during_reroute': downgradedDuringReroute,
      },
      'art20': {
        'meal': gotMeals,
        'hotel': gotHotel,
        'transport': gotTransport,
        'own_expenses': ownExpenses,
        'delay_confirmation': delayConfirmation,
        'extraordinary': extraordinary,
        'extraordinary_type': extraordinaryType,
        'needs_wheelchair': needsWheelchair,
        'needs_escort': needsEscort,
        'other_needs': otherNeedsCtrl.text,
      },
      'tickets': ticketPayload,
      'receipts': receiptsPayload,
      'compensation': {
        'choice': compensationChoice,
        'price': refundAmountCtrl.text,
        'currency': refundCurrencyCtrl.text,
      },
      if (representativePayload != null)
        'representative': representativePayload,
      'art18Eligible': artEligible,
      'art20Eligible': artEligible,
      'forceMajeure': forceMajeureReason,
    };
  }

  @override
  Widget build(BuildContext context) {
    final effectiveDelay = _effectiveDelay();
    final isCancellation = eventType == 'cancellation';
    final isMissed = missedConnection;
    final artEligible = isCancellation || isMissed || effectiveDelay >= 60;

    return Scaffold(
      appBar: AppBar(title: const Text('Case Close')),
      body: Stepper(
        type: StepperType.vertical,
        currentStep: currentStep,
        onStepContinue: _next,
        onStepCancel: _back,
        controlsBuilder: (context, details) {
          final isLast = currentStep == 6;
          return Row(
            children: [
              ElevatedButton(
                onPressed: details.onStepContinue,
                child: Text(isLast ? 'Indsend' : 'Næste'),
              ),
              const SizedBox(width: 8),
              if (currentStep > 0)
                TextButton(
                  onPressed: details.onStepCancel,
                  child: const Text('Tilbage'),
                ),
            ],
          );
        },
        steps: [
          Step(
            title: const Text('1. Rejsestatus'),
            state: _stateFor(0),
            isActive: currentStep >= 0,
            content: Column(
              children: [
                RadioListTile<String>(
                  title: const Text('Rejsen er afsluttet'),
                  value: 'completed',
                  groupValue: travelStatus,
                  onChanged: (v) => setState(() => travelStatus = v),
                ),
                RadioListTile<String>(
                  title: const Text('Rejsen er i gang'),
                  value: 'in_progress',
                  groupValue: travelStatus,
                  onChanged: (v) => setState(() => travelStatus = v),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: depCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Afgang station',
                  ),
                ),
                TextField(
                  controller: arrCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Ankomst station',
                  ),
                ),
                TextField(
                  controller: depTimeCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Planlagt afgang (ISO)',
                  ),
                ),
                TextField(
                  controller: arrTimeCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Planlagt ankomst (ISO)',
                  ),
                ),
                TextField(
                  controller: ticketTypeCtrl,
                  decoration: const InputDecoration(labelText: 'Billettype'),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('2. Hændelse og forsinkelse'),
            state: _stateFor(1),
            isActive: currentStep >= 1,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                RadioListTile<String>(
                  title: const Text('Forsinkelse'),
                  value: 'delay',
                  groupValue: eventType,
                  onChanged: (v) => setState(() => eventType = v),
                ),
                RadioListTile<String>(
                  title: const Text('Aflysning'),
                  value: 'cancellation',
                  groupValue: eventType,
                  onChanged: (v) => setState(() => eventType = v),
                ),
                CheckboxListTile(
                  title: const Text('Mistet forbindelse (kan kombineres)'),
                  value: missedConnection,
                  onChanged: (v) =>
                      setState(() => missedConnection = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                if (missedConnection)
                  TextField(
                    controller: connectingStationCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Station for den missede forbindelse',
                    ),
                  ),
                if (travelStatus == 'completed')
                  TextField(
                    controller: delayCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Faktisk forsinkelse (min)',
                    ),
                    keyboardType: TextInputType.number,
                  )
                else
                  TextField(
                    controller: expectedDelayCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Forventet forsinkelse (min)',
                    ),
                    keyboardType: TextInputType.number,
                  ),
                const SizedBox(height: 8),
                Text(
                  artEligible
                      ? 'Art.18/20: Klar til kompensation/assistance (aflysning/missed eller ≥60 min).'
                      : 'Art.18/20: Aktiveres ved aflysning/missed eller forsinkelse ≥60 min.',
                  style: TextStyle(
                    color: artEligible ? Colors.green : Colors.orange,
                  ),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('3. Art.9 – PMR og cykel'),
            state: _stateFor(2),
            isActive: currentStep >= 2,
            content: Column(
              children: [
                CheckboxListTile(
                  title: const Text('Person med nedsat mobilitet (PMR)?'),
                  value: pmr,
                  onChanged: (v) => setState(() => pmr = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                if (pmr)
                  CheckboxListTile(
                    title: const Text('Var assistancen forudbestilt?'),
                    value: pmrPrebooked,
                    onChanged: (v) => setState(() => pmrPrebooked = v ?? false),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                CheckboxListTile(
                  title: const Text('Rejser du med cykel?'),
                  value: withBike,
                  onChanged: (v) => setState(() => withBike = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
              ],
            ),
          ),
          Step(
            title: const Text('4. Art.18 (refusion/omlægning)'),
            state: _stateFor(3),
            isActive: currentStep >= 3,
            content: Column(
              children: [
                if (!artEligible)
                  const Text(
                    'Art.18 er normalt kun relevant ved aflysning/missed eller forsinkelse ≥60 min.',
                    style: TextStyle(color: Colors.orange),
                  ),
                SwitchListTile(
                  title: const Text(
                    'Aflys hele rejsen og retur til udgangspunkt',
                  ),
                  value: tripCancelReturn,
                  onChanged: artEligible
                      ? (v) => setState(() => tripCancelReturn = v)
                      : null,
                ),
                const Divider(),
                const Text('Refusion'),
                RadioListTile<String>(
                  title: const Text('Anmodet om refusion: Ja'),
                  value: 'yes',
                  groupValue: refundRequested,
                  onChanged: (v) =>
                      setState(() => refundRequested = v ?? 'yes'),
                ),
                RadioListTile<String>(
                  title: const Text('Anmodet om refusion: Nej'),
                  value: 'no',
                  groupValue: refundRequested,
                  onChanged: (v) => setState(() => refundRequested = v ?? 'no'),
                ),
                RadioListTile<String>(
                  title: const Text('Anmodet om refusion: Ved ikke'),
                  value: 'unknown',
                  groupValue: refundRequested,
                  onChanged: (v) =>
                      setState(() => refundRequested = v ?? 'unknown'),
                ),
                if (refundRequested == 'yes')
                  TextField(
                    decoration: const InputDecoration(
                      labelText: 'Refusionstype (kontant/voucher/andet)',
                    ),
                    onChanged: (v) => refundForm = v,
                  ),
                const Divider(),
                const Text('Omlægning'),
                CheckboxListTile(
                  title: const Text('Omlægning ved først givne lejlighed'),
                  value: rerouteSame,
                  onChanged: (v) => setState(() => rerouteSame = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                CheckboxListTile(
                  title: const Text('Omlægning til senere tidspunkt'),
                  value: rerouteLater,
                  onChanged: (v) => setState(() => rerouteLater = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                DropdownButtonFormField<String>(
                  initialValue: informedWithin100,
                  decoration: const InputDecoration(
                    labelText: 'Informerede operatøren inden for 100 min?',
                  ),
                  items: const [
                    DropdownMenuItem(value: 'yes', child: Text('Ja')),
                    DropdownMenuItem(value: 'no', child: Text('Nej')),
                    DropdownMenuItem(value: 'unknown', child: Text('Ved ikke')),
                  ],
                  onChanged: (v) =>
                      setState(() => informedWithin100 = v ?? 'unknown'),
                ),
                const Divider(),
                SwitchListTile(
                  title: const Text('Ekstra udgifter ved omlægning'),
                  value: extraCosts,
                  onChanged: (v) => setState(() => extraCosts = v),
                ),
                if (extraCosts) ...[
                  TextField(
                    controller: extraCostAmountCtrl,
                    decoration: const InputDecoration(labelText: 'Beløb'),
                    keyboardType: TextInputType.number,
                  ),
                  TextField(
                    controller: extraCostCurrencyCtrl,
                    decoration: const InputDecoration(labelText: 'Valuta'),
                  ),
                ],
                CheckboxListTile(
                  title: const Text('Nedklassificeret under omlægning'),
                  value: downgradedDuringReroute,
                  onChanged: (v) =>
                      setState(() => downgradedDuringReroute = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
              ],
            ),
          ),
          Step(
            title: const Text('5. Art.20 og udlæg'),
            state: _stateFor(4),
            isActive: currentStep >= 4,
            content: Column(
              children: [
                if (!artEligible)
                  const Text(
                    'Assistance (Art.20) kræver aflysning/missed eller forsinkelse ≥60 min.',
                    style: TextStyle(color: Colors.orange),
                  ),
                SwitchListTile(
                  title: const Text(
                    'Spring over assistance (intet at indtaste)',
                  ),
                  value: skipAssistance,
                  onChanged: (v) => setState(() => skipAssistance = v),
                ),
                if (!skipAssistance) ...[
                  SwitchListTile(
                    title: const Text('Fik du mad/forfriskninger?'),
                    value: gotMeals,
                    onChanged: artEligible
                        ? (v) => setState(() => gotMeals = v)
                        : null,
                  ),
                  SwitchListTile(
                    title: const Text('Fik du hotel/overnatning?'),
                    value: gotHotel,
                    onChanged: artEligible
                        ? (v) => setState(() => gotHotel = v)
                        : null,
                  ),
                  SwitchListTile(
                    title: const Text(
                      'Fik du transport (til/fra/destination)?',
                    ),
                    value: gotTransport,
                    onChanged: artEligible
                        ? (v) => setState(() => gotTransport = v)
                        : null,
                  ),
                  CheckboxListTile(
                    title: const Text(
                      'Har du haft udgifter (taxa/bus/hotel/mad)?',
                    ),
                    value: ownExpenses,
                    onChanged: (v) => setState(() {
                      ownExpenses = v ?? false;
                      skipReceipts = !(v ?? false);
                    }),
                  ),
                  if (ownExpenses && !skipReceipts) ...[
                    TextButton.icon(
                      onPressed: _addReceipt,
                      icon: const Icon(Icons.upload),
                      label: const Text('Tilføj kvittering'),
                    ),
                    TextButton(
                      onPressed: () {
                        _addReceipt();
                        if (receipts.isNotEmpty) {
                          final r = receipts.last;
                          r.typeCtrl.text = 'hotel';
                          r.amountCtrl.text = '450';
                          r.currencyCtrl.text = 'DKK';
                          r.dateCtrl.text = DateTime.now().toIso8601String();
                        }
                      },
                      child: const Text('Scan (stub) - autofyld demo'),
                    ),
                    TextButton(
                      onPressed: () async {
                        _addReceipt();
                        if (receipts.isEmpty) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Scanner kvittering...'),
                          ),
                        );
                        final parsed = await receiptService.scanAndParse();
                        final r = receipts.last;
                        r.typeCtrl.text = (parsed['type'] ?? '').toString();
                        r.amountCtrl.text = (parsed['amount'] ?? '').toString();
                        r.currencyCtrl.text = (parsed['currency'] ?? '')
                            .toString();
                        r.dateCtrl.text = (parsed['date'] ?? '').toString();
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('OCR udfyldt (ML Kit)')),
                        );
                      },
                      child: const Text('Scan (ML Kit)'),
                    ),
                    if (receipts.isEmpty)
                      const Text('Ingen bilag tilføjet endnu.'),
                    for (int i = 0; i < receipts.length; i++)
                      _ReceiptTile(
                        item: receipts[i],
                        onRemove: () => _removeReceipt(i),
                      ),
                  ],
                  const Divider(),
                  const Text('Særlige behov / handicapassistance'),
                  CheckboxListTile(
                    title: const Text('Kørestol / mobilitetshjælp'),
                    value: needsWheelchair,
                    onChanged: (v) =>
                        setState(() => needsWheelchair = v ?? false),
                  ),
                  CheckboxListTile(
                    title: const Text(
                      'Ledsager / assistance ved ombordstigning',
                    ),
                    value: needsEscort,
                    onChanged: (v) => setState(() => needsEscort = v ?? false),
                  ),
                  TextField(
                    controller: otherNeedsCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Andre behov (valgfrit)',
                    ),
                    maxLines: 2,
                  ),
                  CheckboxListTile(
                    title: const Text(
                      'Fik du skriftlig bekræftelse på forsinkelsen/aflysningen?',
                    ),
                    value: delayConfirmation,
                    onChanged: (v) =>
                        setState(() => delayConfirmation = v ?? false),
                  ),
                  DropdownButtonFormField<String>(
                    initialValue: extraordinary,
                    decoration: const InputDecoration(
                      labelText:
                          'Henviste operatøren til ekstraordinære forhold?',
                    ),
                    items: const [
                      DropdownMenuItem(value: 'yes', child: Text('Ja')),
                      DropdownMenuItem(value: 'no', child: Text('Nej')),
                      DropdownMenuItem(
                        value: 'unknown',
                        child: Text('Ved ikke'),
                      ),
                    ],
                    onChanged: (v) =>
                        setState(() => extraordinary = v ?? 'unknown'),
                  ),
                  if (extraordinary == 'yes')
                    TextField(
                      decoration: const InputDecoration(
                        labelText: 'Type (vejr/katastrofe/andet)',
                      ),
                      onChanged: (v) => extraordinaryType = v,
                    ),
                ],
              ],
            ),
          ),
          Step(
            title: const Text('6. Billetter (upload/manuel)'),
            state: _stateFor(5),
            isActive: currentStep >= 5,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Tilføj én eller flere billetter (OCR/PNR).'),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ElevatedButton.icon(
                      onPressed: uploadingTicket
                          ? null
                          : () => _uploadTicket(ImageSource.camera),
                      icon: const Icon(Icons.photo_camera),
                      label: const Text('Upload fra kamera'),
                    ),
                    OutlinedButton.icon(
                      onPressed: uploadingTicket
                          ? null
                          : () => _uploadTicket(ImageSource.gallery),
                      icon: const Icon(Icons.photo_library),
                      label: const Text('Upload fra galleri'),
                    ),
                    OutlinedButton.icon(
                      onPressed: _addTicket,
                      icon: const Icon(Icons.add),
                      label: const Text('Tilføj billet manuelt'),
                    ),
                  ],
                ),
                if (uploadingTicket)
                  const Padding(
                    padding: EdgeInsets.only(top: 8.0),
                    child: LinearProgressIndicator(),
                  ),
                if (ticketUploadInfo != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(ticketUploadInfo!),
                  ),
                const SizedBox(height: 8),
                for (int i = 0; i < tickets.length; i++)
                  _TicketTile(
                    index: i,
                    item: tickets[i],
                    onRemove: () => _removeTicket(i),
                    onSelectForUpload: () =>
                        setState(() => ticketUploadTargetIndex = i),
                    selectedForUpload: ticketUploadTargetIndex == i,
                  ),
              ],
            ),
          ),
          Step(
            title: const Text('7. Oversigt og indsend'),
            state: _stateFor(6),
            isActive: currentStep >= 6,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (!artEligible)
                  const Text(
                    'Kompensation (Art.18) kræver aflysning/missed eller forsinkelse ≥60 min.',
                    style: TextStyle(color: Colors.orange),
                  ),
                RadioListTile<String>(
                  title: const Text('Refusion af billet'),
                  value: 'refund',
                  groupValue: compensationChoice,
                  onChanged: artEligible
                      ? (v) => setState(() => compensationChoice = v)
                      : null,
                ),
                RadioListTile<String>(
                  title: const Text('Omlægning nu'),
                  value: 'reroute_now',
                  groupValue: compensationChoice,
                  onChanged: artEligible
                      ? (v) => setState(() => compensationChoice = v)
                      : null,
                ),
                RadioListTile<String>(
                  title: const Text('Omlægning senere / voucher'),
                  value: 'reroute_later',
                  groupValue: compensationChoice,
                  onChanged: artEligible
                      ? (v) => setState(() => compensationChoice = v)
                      : null,
                ),
                TextField(
                  controller: refundAmountCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Billetpris (hvis refusion)',
                  ),
                  keyboardType: TextInputType.number,
                ),
                TextField(
                  controller: refundCurrencyCtrl,
                  decoration: const InputDecoration(labelText: 'Valuta'),
                ),
                const Divider(),
                CheckboxListTile(
                  title: const Text('Jeg ansøger på vegne af en anden'),
                  value: representative,
                  onChanged: (v) => setState(() => representative = v ?? false),
                ),
                if (representative) ...[
                  TextField(
                    controller: repNameCtrl,
                    decoration: const InputDecoration(labelText: 'Navn'),
                  ),
                  TextField(
                    controller: repEmailCtrl,
                    decoration: const InputDecoration(labelText: 'Email'),
                  ),
                  TextField(
                    controller: repPhoneCtrl,
                    decoration: const InputDecoration(labelText: 'Telefon'),
                  ),
                  TextField(
                    controller: repRelationCtrl,
                    decoration: const InputDecoration(labelText: 'Relation'),
                  ),
                  CheckboxListTile(
                    title: const Text(
                      'Jeg bekræfter samtykke til at indsende på vegne af passageren',
                    ),
                    value: repConsent,
                    onChanged: (v) => setState(() => repConsent = v ?? false),
                  ),
                ],
                DropdownButtonFormField<String>(
                  initialValue: forceMajeureReason,
                  decoration: const InputDecoration(
                    labelText: 'Force majeure årsag (valgfri)',
                  ),
                  items: const [
                    DropdownMenuItem(value: 'weather', child: Text('Vejr')),
                    DropdownMenuItem(value: 'strike', child: Text('Strejke')),
                    DropdownMenuItem(
                      value: 'security',
                      child: Text('Sikkerhedshændelse'),
                    ),
                    DropdownMenuItem(value: 'other', child: Text('Andet')),
                  ],
                  onChanged: (v) => setState(() => forceMajeureReason = v),
                ),
                const Divider(),
                _summaryRow('Status', travelStatus ?? 'Ikke valgt'),
                _summaryRow('Rejse', '${depCtrl.text} -> ${arrCtrl.text}'),
                _summaryRow('Hændelse', eventType ?? 'Ikke valgt'),
                _summaryRow(
                  'Mistet forbindelse',
                  missedConnection ? 'Ja' : 'Nej',
                ),
                if (missedConnection && connectingStationCtrl.text.isNotEmpty)
                  _summaryRow('Missed station', connectingStationCtrl.text),
                _summaryRow(
                  travelStatus == 'completed'
                      ? 'Faktisk forsinkelse'
                      : 'Forventet forsinkelse',
                  travelStatus == 'completed'
                      ? delayCtrl.text
                      : expectedDelayCtrl.text,
                ),
                _summaryRow('PMR', pmr ? 'Ja' : 'Nej'),
                if (pmr)
                  _summaryRow('PMR forudbestilt', pmrPrebooked ? 'Ja' : 'Nej'),
                _summaryRow('Cykel', withBike ? 'Ja' : 'Nej'),
                _summaryRow('Billetter', '${tickets.length} stk'),
                _summaryRow('Bilag', '${receipts.length} stk'),
                _summaryRow('Assistance', _assistSummary()),
                _summaryRow('Art.18: refusion?', refundRequested),
                _summaryRow(
                  'Art.18: omlægning nu/senere',
                  '${rerouteSame ? 'nu' : ''} ${rerouteLater ? 'senere' : ''}'
                          .trim()
                          .isEmpty
                      ? '-'
                      : '${rerouteSame ? 'nu' : ''} ${rerouteLater ? 'senere' : ''}',
                ),
                _summaryRow(
                  'Art.20: egne udgifter',
                  ownExpenses ? 'Ja' : 'Nej',
                ),
                _summaryRow('Ekstraordinære forhold', extraordinary),
                _summaryRow('Kompensation', compensationChoice ?? 'Ikke valgt'),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    OutlinedButton.icon(
                      onPressed: _previewJson,
                      icon: const Icon(Icons.visibility),
                      label: const Text('Forhåndsvis JSON'),
                    ),
                    OutlinedButton.icon(
                      onPressed: _copyJson,
                      icon: const Icon(Icons.copy),
                      label: const Text('Kopier JSON'),
                    ),
                    ElevatedButton.icon(
                      onPressed: evaluating ? null : _evaluateOnServer,
                      icon: const Icon(Icons.play_arrow),
                      label: const Text('Evaluer (server)'),
                    ),
                  ],
                ),
                if (evaluating)
                  const Padding(
                    padding: EdgeInsets.only(top: 8.0),
                    child: LinearProgressIndicator(),
                  ),
                if (evalError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(
                      evalError!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                if (evalResult != null) ...[
                  const SizedBox(height: 8),
                  const Text('Evaluering (uddrag):'),
                  Builder(
                    builder: (context) {
                      final comp =
                          (evalResult!['compensation'] ?? {})
                              as Map<String, dynamic>;
                      final minutes = (comp['minutes'] ?? '').toString();
                      final amount = (comp['amount'] ?? '').toString();
                      final curr = (comp['currency'] ?? '').toString();
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _summaryRow('Minutters forsinkelse', minutes),
                          _summaryRow(
                            'Kompensation',
                            amount.isEmpty ? '-' : '$amount $curr',
                          ),
                        ],
                      );
                    },
                  ),
                ],
                const SizedBox(height: 12),
                const Text('Tryk Indsend for at sende kravet.'),
                if (submitSuccess != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(
                      submitSuccess!,
                      style: const TextStyle(color: Colors.green),
                    ),
                  ),
                if (submitError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(
                      submitError!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TicketItem {
  final TextEditingController numberCtrl = TextEditingController();
  final TextEditingController priceCtrl = TextEditingController();
  final TextEditingController currencyCtrl = TextEditingController(text: 'DKK');
  final TextEditingController typeCtrl = TextEditingController();
  final TextEditingController depCtrl = TextEditingController();
  final TextEditingController arrCtrl = TextEditingController();
  final TextEditingController depTimeCtrl = TextEditingController();
  final TextEditingController arrTimeCtrl = TextEditingController();
  bool throughTicket = false;
  bool downgraded = false;

  void dispose() {
    numberCtrl.dispose();
    priceCtrl.dispose();
    currencyCtrl.dispose();
    typeCtrl.dispose();
    depCtrl.dispose();
    arrCtrl.dispose();
    depTimeCtrl.dispose();
    arrTimeCtrl.dispose();
  }
}

class _TicketTile extends StatelessWidget {
  final int index;
  final _TicketItem item;
  final VoidCallback onRemove;
  final VoidCallback onSelectForUpload;
  final bool selectedForUpload;

  const _TicketTile({
    required this.index,
    required this.item,
    required this.onRemove,
    required this.onSelectForUpload,
    required this.selectedForUpload,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(8.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Billet #${index + 1}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                Row(
                  children: [
                    ChoiceChip(
                      label: const Text('Upload hertil'),
                      selected: selectedForUpload,
                      onSelected: (_) => onSelectForUpload(),
                    ),
                    const SizedBox(width: 8),
                    if (index > 0)
                      IconButton(
                        onPressed: onRemove,
                        icon: const Icon(Icons.delete),
                      ),
                  ],
                ),
              ],
            ),
            TextField(
              controller: item.numberCtrl,
              decoration: const InputDecoration(
                labelText: 'Billet-/PNR-nummer',
              ),
            ),
            TextField(
              controller: item.priceCtrl,
              decoration: const InputDecoration(labelText: 'Billetpris'),
              keyboardType: TextInputType.number,
            ),
            TextField(
              controller: item.currencyCtrl,
              decoration: const InputDecoration(labelText: 'Valuta'),
            ),
            TextField(
              controller: item.typeCtrl,
              decoration: const InputDecoration(
                labelText: 'Billettype/produkt',
              ),
            ),
            TextField(
              controller: item.depCtrl,
              decoration: const InputDecoration(labelText: 'Afgang (station)'),
            ),
            TextField(
              controller: item.arrCtrl,
              decoration: const InputDecoration(labelText: 'Ankomst (station)'),
            ),
            TextField(
              controller: item.depTimeCtrl,
              decoration: const InputDecoration(
                labelText: 'Planlagt afgang (ISO)',
              ),
            ),
            TextField(
              controller: item.arrTimeCtrl,
              decoration: const InputDecoration(
                labelText: 'Planlagt ankomst (ISO)',
              ),
            ),
            SwitchListTile(
              title: const Text('Gennemgående billet'),
              value: item.throughTicket,
              onChanged: (v) => item.throughTicket = v,
            ),
            CheckboxListTile(
              title: const Text('Nedklassificeret'),
              value: item.downgraded,
              onChanged: (v) => item.downgraded = v ?? false,
              controlAffinity: ListTileControlAffinity.leading,
            ),
          ],
        ),
      ),
    );
  }
}

class _ReceiptItem {
  String? category;
  final TextEditingController amountCtrl = TextEditingController();
  final TextEditingController currencyCtrl = TextEditingController(text: 'DKK');
  final TextEditingController dateCtrl = TextEditingController();
  final TextEditingController typeCtrl = TextEditingController();

  void dispose() {
    amountCtrl.dispose();
    currencyCtrl.dispose();
    dateCtrl.dispose();
    typeCtrl.dispose();
  }
}

class _ReceiptTile extends StatelessWidget {
  final _ReceiptItem item;
  final VoidCallback onRemove;
  const _ReceiptTile({required this.item, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    const categories = <String>[
      'mad/forfriskninger',
      'hotel/overnatning',
      'transport til/fra station',
      'transport til destination',
      'cykel/bagage',
      'pmr/hjælpemidler',
      'andet',
    ];
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(8.0),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Bilag'),
                IconButton(onPressed: onRemove, icon: const Icon(Icons.delete)),
              ],
            ),
            TextField(
              controller: item.typeCtrl,
              decoration: const InputDecoration(
                labelText: 'Type (hotel/mad/taxi)',
              ),
            ),
            DropdownButtonFormField<String>(
              initialValue: item.category,
              decoration: const InputDecoration(labelText: 'Kategori'),
              items: categories
                  .map(
                    (c) => DropdownMenuItem<String>(value: c, child: Text(c)),
                  )
                  .toList(),
              onChanged: (v) => item.category = v,
            ),
            TextField(
              controller: item.amountCtrl,
              decoration: const InputDecoration(labelText: 'Beløb'),
              keyboardType: TextInputType.number,
            ),
            TextField(
              controller: item.currencyCtrl,
              decoration: const InputDecoration(labelText: 'Valuta'),
            ),
            TextField(
              controller: item.dateCtrl,
              decoration: const InputDecoration(labelText: 'Dato (ISO)'),
            ),
          ],
        ),
      ),
    );
  }
}
