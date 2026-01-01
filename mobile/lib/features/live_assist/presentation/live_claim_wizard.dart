import 'package:flutter/material.dart';

/// Lightweight cache for storing the wizard state per journey.
/// Replace the Map + TODO with SharedPreferences or a local DB when persisting.
class CaseDraftStorage {
  static final Map<String, Map<String, dynamic>> _drafts = {};

  static Future<void> saveDraft(String journeyId, Map<String, dynamic> payload) async {
    _drafts[journeyId] = Map.of(payload);
    // TODO: persist to SharedPreferences / secure storage to survive app restarts.
  }

  static Map<String, dynamic>? loadDraft(String journeyId) => _drafts[journeyId];
}

class LiveClaimWizard extends StatefulWidget {
  final String journeyId;
  final Map<String, dynamic>? existingDraft;

  const LiveClaimWizard({
    super.key,
    required this.journeyId,
    this.existingDraft,
  });

  @override
  State<LiveClaimWizard> createState() => _LiveClaimWizardState();
}

class _LiveClaimWizardState extends State<LiveClaimWizard> {
  int currentStep = 0;
  String? travelStatus;
  String? eventType;
  bool expectsDelayOver60 = false;
  bool tripCancelReturn = false;
  String refundRequested = 'unknown';
  bool rerouteNow = false;
  bool rerouteLater = false;
  String informedWithin100 = 'unknown';
  bool extraCosts = false;
  final TextEditingController extraCostAmountCtrl = TextEditingController();
  final TextEditingController extraCostCurrencyCtrl = TextEditingController(text: 'DKK');

  @override
  void initState() {
    super.initState();
    final draft = widget.existingDraft ?? CaseDraftStorage.loadDraft(widget.journeyId);
    if (draft != null) {
      travelStatus = draft['travelStatus'] as String?;
      eventType = draft['eventType'] as String?;
      expectsDelayOver60 = draft['delay60'] as bool? ?? false;
      tripCancelReturn = draft['art18']?['trip_cancel_return'] as bool? ?? false;
      refundRequested = draft['art18']?['refund_requested'] as String? ?? 'unknown';
      rerouteNow = draft['art18']?['reroute_now'] as bool? ?? false;
      rerouteLater = draft['art18']?['reroute_later'] as bool? ?? false;
      informedWithin100 = draft['art18']?['informed_within_100'] as String? ?? 'unknown';
      extraCosts = draft['art18']?['extra_costs'] as bool? ?? false;
      extraCostAmountCtrl.text = draft['art18']?['extra_cost_amount'] ?? '';
      extraCostCurrencyCtrl.text = draft['art18']?['extra_cost_currency'] ?? 'DKK';
    }
  }

  @override
  void dispose() {
    extraCostAmountCtrl.dispose();
    extraCostCurrencyCtrl.dispose();
    super.dispose();
  }

  void _persistDraft() {
    final payload = {
      'travelStatus': travelStatus,
      'eventType': eventType,
      'delay60': expectsDelayOver60,
      'art18': {
        'trip_cancel_return': tripCancelReturn,
        'refund_requested': refundRequested,
        'reroute_now': rerouteNow,
        'reroute_later': rerouteLater,
        'informed_within_100': informedWithin100,
        'extra_costs': extraCosts,
        'extra_cost_amount': extraCostAmountCtrl.text,
        'extra_cost_currency': extraCostCurrencyCtrl.text,
      },
    };
    CaseDraftStorage.saveDraft(widget.journeyId, payload);
  }

  void _next() {
    if (currentStep == 2 && !expectsDelayOver60 && eventType != 'cancellation') {
      // Skip Art.18 if no qualifying event
      _finish();
      return;
    }
    setState(() {
      if (currentStep < 3) {
        currentStep++;
      } else {
        _finish();
      }
    });
    _persistDraft();
  }

  void _finish() {
    _persistDraft();
    Navigator.pop(context, {
      'journeyId': widget.journeyId,
      'travelStatus': travelStatus,
      'eventType': eventType,
      'delay60': expectsDelayOver60,
      'art18': {
        'trip_cancel_return': tripCancelReturn,
        'refund_requested': refundRequested,
        'reroute_now': rerouteNow,
        'reroute_later': rerouteLater,
        'informed_within_100': informedWithin100,
        'extra_costs': extraCosts,
        'extra_cost_amount': extraCostAmountCtrl.text,
        'extra_cost_currency': extraCostCurrencyCtrl.text,
      },
    });
  }

  void _back() {
    if (currentStep > 0) {
      setState(() {
        currentStep--;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final shouldShowArt18 = eventType == 'cancellation' || expectsDelayOver60;
    return Scaffold(
      appBar: AppBar(title: const Text('Registrer hændelse')),
      body: Stepper(
        type: StepperType.vertical,
        currentStep: currentStep,
        onStepContinue: _next,
        onStepCancel: _back,
        controlsBuilder: (context, details) => Row(
          children: [
            ElevatedButton(
              onPressed: details.onStepContinue,
              child: Text(currentStep == 3 ? 'Udfør' : 'Næste'),
            ),
            const SizedBox(width: 8),
            if (currentStep > 0)
              TextButton(
                onPressed: details.onStepCancel,
                child: const Text('Tilbage'),
              ),
          ],
        ),
        steps: [
          Step(
            title: const Text('1. Rejsestatus'),
            state: currentStep > 0 ? StepState.complete : StepState.editing,
            isActive: true,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                RadioListTile<String>(
                  title: const Text('Afsluttet'),
                  value: 'done',
                  groupValue: travelStatus,
                  onChanged: (v) => setState(() => travelStatus = v),
                ),
                RadioListTile<String>(
                  title: const Text('I gang'),
                  value: 'in_progress',
                  groupValue: travelStatus,
                  onChanged: (v) => setState(() => travelStatus = v),
                ),
                const SizedBox(height: 8),
                Text(
                  'Kun “afsluttet” eller “i gang” er relevante til Live Claim flowet.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Step(
            title: const Text('2. Hændelse'),
            state: currentStep > 1 ? StepState.complete : StepState.editing,
            isActive: currentStep >= 1,
            content: Column(
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
                  title: const Text('Forventer forsinkelse ≥ 60 min'),
                  value: expectsDelayOver60,
                  onChanged: (v) => setState(() => expectsDelayOver60 = v ?? false),
                ),
              ],
            ),
          ),
          Step(
            title: const Text('3. Art.18 (refusion/omlægning)'),
            state: currentStep > 2 ? StepState.complete : StepState.editing,
            isActive: currentStep >= 2,
            content: shouldShowArt18
                ? Column(
                    children: [
                      SwitchListTile(
                        title: const Text('Aflys rejsen og retur til start'),
                        value: tripCancelReturn,
                        onChanged: (v) => setState(() => tripCancelReturn = v),
                      ),
                      const Divider(),
                      const Text('Refusion'),
                      RadioListTile<String>(
                        title: const Text('Ja'),
                        value: 'yes',
                        groupValue: refundRequested,
                        onChanged: (v) => setState(() => refundRequested = v ?? 'yes'),
                      ),
                      RadioListTile<String>(
                        title: const Text('Nej'),
                        value: 'no',
                        groupValue: refundRequested,
                        onChanged: (v) => setState(() => refundRequested = v ?? 'no'),
                      ),
                      const Divider(),
                      const Text('Omlægning'),
                      SwitchListTile(
                        title: const Text('Ved første lejlighed'),
                        value: rerouteNow,
                        onChanged: (v) => setState(() => rerouteNow = v ?? false),
                      ),
                      SwitchListTile(
                        title: const Text('Senere tidspunkt'),
                        value: rerouteLater,
                        onChanged: (v) => setState(() => rerouteLater = v ?? false),
                      ),
                      DropdownButtonFormField<String>(
                        value: informedWithin100,
                        decoration: const InputDecoration(labelText: 'Informeret inden for 100 min?'),
                        items: const [
                          DropdownMenuItem(value: 'yes', child: Text('Ja')),
                          DropdownMenuItem(value: 'no', child: Text('Nej')),
                          DropdownMenuItem(value: 'unknown', child: Text('Ved ikke')),
                        ],
                        onChanged: (v) => setState(() => informedWithin100 = v ?? 'unknown'),
                      ),
                      const Divider(),
                      SwitchListTile(
                        title: const Text('Ekstra udgifter ved omlægning'),
                        value: extraCosts,
                        onChanged: (v) => setState(() => extraCosts = v ?? false),
                      ),
                      if (extraCosts)
                        Column(
                          children: [
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
                        ),
                    ],
                  )
                : const Text('Art.18 spørgsmål aktiveres kun ved aflysning eller forsinkelse ≥ 60 minutter.'),
          ),
          Step(
            title: const Text('4. Opsummering'),
            state: currentStep == 4 ? StepState.editing : StepState.indexed,
            isActive: currentStep >= 3,
            content: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Rejsestatus: ${travelStatus ?? 'Ikke valgt'}'),
                Text('Hændelse: ${eventType ?? 'Ikke valgt'}'),
                Text('Art.18 refusion: $refundRequested'),
                Text('Reroute nu: ${rerouteNow ? 'Ja' : 'Nej'}'),
                const SizedBox(height: 8),
                const Text('Tryk Udfør for at gemme og returnere data.'),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
