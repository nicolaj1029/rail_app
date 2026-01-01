import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/journeys/data/manual_journeys_service.dart';

class ManualJourneyScreen extends StatefulWidget {
  final String deviceId;
  const ManualJourneyScreen({super.key, required this.deviceId});

  @override
  State<ManualJourneyScreen> createState() => _ManualJourneyScreenState();
}

class _ManualJourneyScreenState extends State<ManualJourneyScreen> {
  final _formKey = GlobalKey<FormState>();
  final fromCtrl = TextEditingController();
  final toCtrl = TextEditingController();
  final depCtrl = TextEditingController();
  final arrCtrl = TextEditingController();
  String ticketType = 'enkelt';
  String disruption = 'delay';
  final delayCtrl = TextEditingController();
  XFile? ticketFile;
  bool submitting = false;
  String? error;
  String? success;

  @override
  void dispose() {
    fromCtrl.dispose();
    toCtrl.dispose();
    depCtrl.dispose();
    arrCtrl.dispose();
    delayCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickTicket(ImageSource source) async {
    final picker = ImagePicker();
    final f = await picker.pickImage(source: source, imageQuality: 85);
    if (f != null) {
      setState(() {
        ticketFile = f;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      submitting = true;
      error = null;
      success = null;
    });
    try {
      final payload = <String, dynamic>{
        'device_id': widget.deviceId,
        'from_station': fromCtrl.text.trim(),
        'to_station': toCtrl.text.trim(),
        'dep_time': depCtrl.text.trim(),
        'arr_time': arrCtrl.text.trim(),
        'ticket_type': ticketType,
        'disruption_type': disruption,
        'delay_minutes': delayCtrl.text.trim(),
      };
      if (ticketFile != null) {
        final bytes = await ticketFile!.readAsBytes();
        payload['ticket_image_base64'] = base64Encode(bytes);
        payload['ticket_filename'] = ticketFile!.name;
      }
      final svc = ManualJourneysService(baseUrl: apiBaseUrl);
      final res = await svc.submit(payload);
      setState(() {
        success = 'Indsendt: ${res['status'] ?? res}';
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Manuel rejse sendt')));
    } catch (e) {
      setState(() {
        error = '$e';
      });
    } finally {
      setState(() {
        submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Anmeld rejse manuelt')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: fromCtrl,
                decoration: const InputDecoration(labelText: 'Fra-station'),
                validator: (v) => v == null || v.isEmpty ? 'Påkrævet' : null,
              ),
              TextFormField(
                controller: toCtrl,
                decoration: const InputDecoration(labelText: 'Til-station'),
                validator: (v) => v == null || v.isEmpty ? 'Påkrævet' : null,
              ),
              TextFormField(
                controller: depCtrl,
                decoration: const InputDecoration(labelText: 'Afgangstid (ISO)'),
              ),
              TextFormField(
                controller: arrCtrl,
                decoration: const InputDecoration(labelText: 'Ankomsttid (valgfri)'),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                initialValue: ticketType,
                decoration: const InputDecoration(labelText: 'Billettype'),
                items: const [
                  DropdownMenuItem(value: 'enkelt', child: Text('Enkelt')),
                  DropdownMenuItem(value: 'periode', child: Text('Periodekort')),
                  DropdownMenuItem(value: 'rejsekort', child: Text('Rejsekort')),
                ],
                onChanged: (v) => setState(() => ticketType = v ?? 'enkelt'),
              ),
              const SizedBox(height: 8),
              const Text('Hændelse'),
              RadioListTile<String>(
                title: const Text('Forsinkelse'),
                value: 'delay',
                groupValue: disruption,
                onChanged: (v) => setState(() => disruption = v ?? 'delay'),
              ),
              RadioListTile<String>(
                title: const Text('Aflysning'),
                value: 'cancelled',
                groupValue: disruption,
                onChanged: (v) => setState(() => disruption = v ?? 'cancelled'),
              ),
              RadioListTile<String>(
                title: const Text('Mistet forbindelse'),
                value: 'missed_connection',
                groupValue: disruption,
                onChanged: (v) => setState(() => disruption = v ?? 'missed_connection'),
              ),
              TextFormField(
                controller: delayCtrl,
                decoration: const InputDecoration(labelText: 'Faktisk forsinkelse (min)'),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  ElevatedButton.icon(
                    onPressed: () => _pickTicket(ImageSource.camera),
                    icon: const Icon(Icons.photo_camera),
                    label: const Text('Foto billet'),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton.icon(
                    onPressed: () => _pickTicket(ImageSource.gallery),
                    icon: const Icon(Icons.file_upload),
                    label: Text(ticketFile == null ? 'Vælg fil' : 'Valgt: ${ticketFile!.name}'),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (error != null) Text(error!, style: const TextStyle(color: Colors.red)),
              if (success != null) Text(success!, style: const TextStyle(color: Colors.green)),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: submitting ? null : _submit,
                child: Text(submitting ? 'Sender...' : 'Indsend rejse og krav'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
