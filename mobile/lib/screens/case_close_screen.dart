import 'package:flutter/material.dart';

import '../config.dart';
import '../services/journeys_service.dart';
import '../services/receipt_service.dart';

class CaseCloseScreen extends StatefulWidget {
  final Map<String, dynamic> journey;
  const CaseCloseScreen({super.key, required this.journey});

  @override
  State<CaseCloseScreen> createState() => _CaseCloseScreenState();
}

class _CaseCloseScreenState extends State<CaseCloseScreen> {
  int currentStep = 0;
  bool submitting = false;
  String? submitError;
  String? submitSuccess;
  final ReceiptService receiptService = ReceiptService();

  // Step 1
  late TextEditingController depCtrl;
  late TextEditingController arrCtrl;
  late TextEditingController depTimeCtrl;
  late TextEditingController arrTimeCtrl;
  late TextEditingController ticketTypeCtrl;

  // Step 2
  String? eventType;
  final TextEditingController delayCtrl = TextEditingController();

  // Step 3
  final List<_ReceiptItem> receipts = [];

  // Step 4
  bool gotMeals = false;
  bool gotHotel = false;
  bool gotTransport = false;
  bool selfPaidMeals = false;
  bool selfPaidHotel = false;
  bool selfPaidTransport = false;

  // Step 5
  String? compensationChoice;
  final TextEditingController refundAmountCtrl = TextEditingController();
  final TextEditingController refundCurrencyCtrl = TextEditingController(text: 'DKK');

  @override
  void initState() {
    super.initState();
    final j = widget.journey;
    depCtrl = TextEditingController(text: (j['dep_station'] ?? j['start'] ?? '').toString());
    arrCtrl = TextEditingController(text: (j['arr_station'] ?? j['end'] ?? '').toString());
    depTimeCtrl = TextEditingController(text: (j['dep_time'] ?? '').toString());
    arrTimeCtrl = TextEditingController(text: (j['arr_time'] ?? '').toString());
    ticketTypeCtrl = TextEditingController(text: (j['ticket_type'] ?? '').toString());
  }

  @override
  void dispose() {
    depCtrl.dispose();
    arrCtrl.dispose();
    depTimeCtrl.dispose();
    arrTimeCtrl.dispose();
    ticketTypeCtrl.dispose();
    delayCtrl.dispose();
    refundAmountCtrl.dispose();
    refundCurrencyCtrl.dispose();
    for (final r in receipts) {
      r.dispose();
    }
    super.dispose();
  }

  void _addReceipt() {
    setState(() {
      receipts.add(_ReceiptItem());
    });
  }

  void _removeReceipt(int index) {
    setState(() {
      receipts.removeAt(index);
    });
  }

  void _next() {
    if (currentStep < 5) {
      setState(() {
        currentStep += 1;
      });
    } else {
      _submit();
    }
  }

  void _back() {
    if (currentStep > 0) {
      setState(() {
        currentStep -= 1;
      });
    }
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
    svc.submit(id, payload).then((res) {
      setState(() {
        submitSuccess = 'Indsendt: ${res['data'] ?? res}';
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indsendt (stub)')),
      );
    }).catchError((e) {
      setState(() {
        submitError = '$e';
      });
    }).whenComplete(() {
      setState(() {
        submitting = false;
      });
    });
  }

  StepState _stateFor(int step) {
    if (step < currentStep) return StepState.complete;
    if (step == currentStep) return StepState.editing;
    return StepState.indexed;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Case Close')),
      body: Stepper(
        type: StepperType.vertical,
        currentStep: currentStep,
        onStepContinue: _next,
        onStepCancel: _back,
        controlsBuilder: (context, details) {
          final isLast = currentStep == 5;
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
            title: const Text('1. Bekræft rejse'),
            state: _stateFor(0),
            isActive: currentStep >= 0,
            content: Column(
              children: [
                TextField(controller: depCtrl, decoration: const InputDecoration(labelText: 'Afgang station')),
                TextField(controller: arrCtrl, decoration: const InputDecoration(labelText: 'Ankomst station')),
                TextField(controller: depTimeCtrl, decoration: const InputDecoration(labelText: 'Planlagt afgang (ISO)')),
                TextField(controller: arrTimeCtrl, decoration: const InputDecoration(labelText: 'Planlagt ankomst (ISO)')),
                TextField(controller: ticketTypeCtrl, decoration: const InputDecoration(labelText: 'Billettype')),
              ],
            ),
          ),
          Step(
            title: const Text('2. Vælg hændelse'),
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
                RadioListTile<String>(
                  title: const Text('Mistet forbindelse'),
                  value: 'missed_connection',
                  groupValue: eventType,
                  onChanged: (v) => setState(() => eventType = v),
                ),
                TextField(
                  controller: delayCtrl,
                  decoration: const InputDecoration(labelText: 'Faktisk forsinkelse (min)'),
                  keyboardType: TextInputType.number,
                ),
              ],
            ),
          ),
          Step(
            title: const Text('3. Upload bilag'),
            state: _stateFor(2),
            isActive: currentStep >= 2,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextButton.icon(
                  onPressed: _addReceipt,
                  icon: const Icon(Icons.upload),
                  label: const Text('Tilføj kvittering'),
                ),
                TextButton(
                  onPressed: () {
                    // stub OCR fill
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
                      const SnackBar(content: Text('Scanner kvittering...')),
                    );
                    final parsed = await receiptService.scanAndParse();
                    final r = receipts.last;
                    r.typeCtrl.text = (parsed['type'] ?? '').toString();
                    r.amountCtrl.text = (parsed['amount'] ?? '').toString();
                    r.currencyCtrl.text = (parsed['currency'] ?? '').toString();
                    r.dateCtrl.text = (parsed['date'] ?? '').toString();
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('OCR udfyldt (ML Kit)')),
                    );
                  },
                  child: const Text('Scan (ML Kit)'),
                ),
                if (receipts.isEmpty) const Text('Ingen bilag tilføjet endnu.'),
                for (int i = 0; i < receipts.length; i++)
                  _ReceiptTile(
                    item: receipts[i],
                    onRemove: () => _removeReceipt(i),
                  ),
              ],
            ),
          ),
          Step(
            title: const Text('4. Assistance'),
            state: _stateFor(3),
            isActive: currentStep >= 3,
            content: Column(
              children: [
                SwitchListTile(
                  title: const Text('Fik du mad/forfriskninger?'),
                  value: gotMeals,
                  onChanged: (v) => setState(() => gotMeals = v),
                ),
                SwitchListTile(
                  title: const Text('Fik du hotel/overnatning?'),
                  value: gotHotel,
                  onChanged: (v) => setState(() => gotHotel = v),
                ),
                SwitchListTile(
                  title: const Text('Fik du transport (til/fra/destination)?'),
                  value: gotTransport,
                  onChanged: (v) => setState(() => gotTransport = v),
                ),
                const Divider(),
                CheckboxListTile(
                  title: const Text('Jeg betalte selv mad'),
                  value: selfPaidMeals,
                  onChanged: (v) => setState(() => selfPaidMeals = v ?? false),
                ),
                CheckboxListTile(
                  title: const Text('Jeg betalte selv hotel'),
                  value: selfPaidHotel,
                  onChanged: (v) => setState(() => selfPaidHotel = v ?? false),
                ),
                CheckboxListTile(
                  title: const Text('Jeg betalte selv transport'),
                  value: selfPaidTransport,
                  onChanged: (v) => setState(() => selfPaidTransport = v ?? false),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('5. Kompensation'),
            state: _stateFor(4),
            isActive: currentStep >= 4,
            content: Column(
              children: [
                RadioListTile<String>(
                  title: const Text('Refusion af billet'),
                  value: 'refund',
                  groupValue: compensationChoice,
                  onChanged: (v) => setState(() => compensationChoice = v),
                ),
                RadioListTile<String>(
                  title: const Text('Omlægning nu'),
                  value: 'reroute_now',
                  groupValue: compensationChoice,
                  onChanged: (v) => setState(() => compensationChoice = v),
                ),
                RadioListTile<String>(
                  title: const Text('Omlægning senere / voucher'),
                  value: 'reroute_later',
                  groupValue: compensationChoice,
                  onChanged: (v) => setState(() => compensationChoice = v),
                ),
                TextField(
                  controller: refundAmountCtrl,
                  decoration: const InputDecoration(labelText: 'Billetpris (hvis refusion)'),
                  keyboardType: TextInputType.number,
                ),
                TextField(
                  controller: refundCurrencyCtrl,
                  decoration: const InputDecoration(labelText: 'Valuta'),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('6. Gennemse og indsend'),
            state: _stateFor(5),
            isActive: currentStep >= 5,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _summaryRow('Rejse', '${depCtrl.text} -> ${arrCtrl.text}'),
                _summaryRow('Hændelse', eventType ?? 'Ikke valgt'),
                _summaryRow('Bilag', '${receipts.length} stk'),
                _summaryRow('Assistance', _assistSummary()),
                _summaryRow('Kompensation', compensationChoice ?? 'Ikke valgt'),
                const SizedBox(height: 12),
                const Text('Tryk Indsend for at sende kravet.'),
                if (submitSuccess != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(submitSuccess!, style: const TextStyle(color: Colors.green)),
                  ),
                if (submitError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8.0),
                    child: Text(submitError!, style: const TextStyle(color: Colors.red)),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        children: [
          Expanded(flex: 2, child: Text(label, style: const TextStyle(fontWeight: FontWeight.bold))),
          Expanded(flex: 3, child: Text(value)),
        ],
      ),
    );
  }

  String _assistSummary() {
    final parts = <String>[];
    if (gotMeals) parts.add('mad');
    if (gotHotel) parts.add('hotel');
    if (gotTransport) parts.add('transport');
    if (selfPaidMeals || selfPaidHotel || selfPaidTransport) parts.add('selvbetalt');
    return parts.isEmpty ? 'Ingen' : parts.join(', ');
  }

  Map<String, dynamic> _buildPayload() {
    return {
      'journey': {
        'dep': depCtrl.text,
        'arr': arrCtrl.text,
        'dep_time': depTimeCtrl.text,
        'arr_time': arrTimeCtrl.text,
        'ticket_type': ticketTypeCtrl.text,
      },
      'event': {
        'type': eventType,
        'delay_minutes': delayCtrl.text,
      },
      'receipts': receipts
          .map((r) => {
                'type': r.typeCtrl.text,
                'amount': r.amountCtrl.text,
                'currency': r.currencyCtrl.text,
                'date': r.dateCtrl.text,
              })
          .toList(),
      'assistance': {
        'got_meals': gotMeals,
        'got_hotel': gotHotel,
        'got_transport': gotTransport,
        'self_paid_meals': selfPaidMeals,
        'self_paid_hotel': selfPaidHotel,
        'self_paid_transport': selfPaidTransport,
      },
      'compensation': {
        'choice': compensationChoice,
        'price': refundAmountCtrl.text,
        'currency': refundCurrencyCtrl.text,
      },
    };
  }
}

class _ReceiptItem {
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
              decoration: const InputDecoration(labelText: 'Type (hotel/mad/taxi)'),
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
