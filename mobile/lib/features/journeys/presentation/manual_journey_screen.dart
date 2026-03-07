import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/journeys/data/manual_journeys_service.dart';
import 'package:mobile/services/stations_service.dart';

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
  final delayCtrl = TextEditingController();

  String ticketType = 'enkelt';
  String disruption = 'delay';
  XFile? ticketFile;
  bool submitting = false;
  String? error;
  String? success;

  late final StationsService _stationsService;

  @override
  void initState() {
    super.initState();
    _stationsService = StationsService(baseUrl: apiBaseUrl);
  }

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
    final file = await picker.pickImage(source: source, imageQuality: 85);
    if (file != null) {
      setState(() {
        ticketFile = file;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

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

      final service = ManualJourneysService(baseUrl: apiBaseUrl);
      final res = await service.submit(payload);
      setState(() {
        success = 'Indsendt: ${res['status'] ?? res}';
      });

      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Manuel rejse sendt')));
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
              _StationLookupField(
                controller: fromCtrl,
                label: 'Fra-station',
                stationsService: _stationsService,
                validator: (v) => v == null || v.isEmpty ? 'Påkrævet' : null,
              ),
              _StationLookupField(
                controller: toCtrl,
                label: 'Til-station',
                stationsService: _stationsService,
                validator: (v) => v == null || v.isEmpty ? 'Påkrævet' : null,
              ),
              TextFormField(
                controller: depCtrl,
                decoration: const InputDecoration(
                  labelText: 'Afgangstid (ISO)',
                ),
              ),
              TextFormField(
                controller: arrCtrl,
                decoration: const InputDecoration(
                  labelText: 'Ankomsttid (valgfri)',
                ),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                initialValue: ticketType,
                decoration: const InputDecoration(labelText: 'Billettype'),
                items: const [
                  DropdownMenuItem(value: 'enkelt', child: Text('Enkelt')),
                  DropdownMenuItem(
                    value: 'periode',
                    child: Text('Periodekort'),
                  ),
                  DropdownMenuItem(
                    value: 'rejsekort',
                    child: Text('Rejsekort'),
                  ),
                ],
                onChanged: (v) => setState(() => ticketType = v ?? 'enkelt'),
              ),
              const SizedBox(height: 8),
              const Text('Hændelse'),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _ManualChoiceChip(
                    label: 'Forsinkelse',
                    selected: disruption == 'delay',
                    onSelected: () => setState(() => disruption = 'delay'),
                  ),
                  _ManualChoiceChip(
                    label: 'Aflysning',
                    selected: disruption == 'cancelled',
                    onSelected: () => setState(() => disruption = 'cancelled'),
                  ),
                  _ManualChoiceChip(
                    label: 'Mistet forbindelse',
                    selected: disruption == 'missed_connection',
                    onSelected: () =>
                        setState(() => disruption = 'missed_connection'),
                  ),
                ],
              ),
              TextFormField(
                controller: delayCtrl,
                decoration: const InputDecoration(
                  labelText: 'Faktisk forsinkelse (min)',
                ),
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
                    label: Text(
                      ticketFile == null
                          ? 'Vælg fil'
                          : 'Valgt: ${ticketFile!.name}',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (error != null)
                Text(error!, style: const TextStyle(color: Colors.red)),
              if (success != null)
                Text(success!, style: const TextStyle(color: Colors.green)),
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

class _StationLookupField extends StatefulWidget {
  final TextEditingController controller;
  final String label;
  final StationsService stationsService;
  final String? Function(String?)? validator;

  const _StationLookupField({
    required this.controller,
    required this.label,
    required this.stationsService,
    this.validator,
  });

  @override
  State<_StationLookupField> createState() => _StationLookupFieldState();
}

class _StationLookupFieldState extends State<_StationLookupField> {
  List<Map<String, dynamic>> _results = const [];
  bool _loading = false;
  String _lastQuery = '';

  Future<void> _search(String value) async {
    final query = value.trim();
    _lastQuery = query;

    if (query.length < 2) {
      if (mounted) {
        setState(() {
          _results = const [];
          _loading = false;
        });
      }
      return;
    }

    setState(() {
      _loading = true;
    });

    try {
      final results = await widget.stationsService.searchStations(query);
      if (!mounted || _lastQuery != query) {
        return;
      }
      setState(() {
        _results = results;
      });
    } catch (_) {
      if (!mounted || _lastQuery != query) {
        return;
      }
      setState(() {
        _results = const [];
      });
    } finally {
      if (mounted && _lastQuery == query) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  void _selectStation(Map<String, dynamic> station) {
    widget.controller.text = (station['name'] ?? '').toString();
    setState(() {
      _results = const [];
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: widget.controller,
          decoration: InputDecoration(
            labelText: widget.label,
            suffixIcon: _loading
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : null,
          ),
          validator: widget.validator,
          onChanged: _search,
        ),
        if (_results.isNotEmpty)
          Card(
            margin: const EdgeInsets.only(top: 8, bottom: 8),
            child: Column(
              children: _results.take(6).map((station) {
                final name = (station['name'] ?? '').toString();
                final country = (station['country'] ?? '').toString();
                return ListTile(
                  dense: true,
                  leading: const Icon(Icons.location_on_outlined),
                  title: Text(name),
                  subtitle: country.isEmpty ? null : Text(country),
                  onTap: () => _selectStation(station),
                );
              }).toList(),
            ),
          ),
      ],
    );
  }
}

class _ManualChoiceChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onSelected;

  const _ManualChoiceChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onSelected(),
    );
  }
}
