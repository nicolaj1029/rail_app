import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/case_close/presentation/case_close_screen.dart';
import 'package:mobile/features/journeys/data/journeys_service.dart';
import 'package:mobile/features/journeys/presentation/journeys_list_screen.dart';
import 'package:mobile/services/api_client.dart';
import 'package:mobile/services/device_service.dart';
import 'package:mobile/services/events_service.dart';
import 'package:mobile/services/notifications_service.dart';
import 'package:mobile/services/shadow_tracker.dart';
import 'package:mobile/services/stations_service.dart';

class LiveAssistScreen extends StatefulWidget {
  const LiveAssistScreen({super.key});

  @override
  State<LiveAssistScreen> createState() => _LiveAssistScreenState();
}

class _LiveAssistScreenState extends State<LiveAssistScreen> {
  late final ApiClient api;
  String? deviceId;
  ShadowTracker? tracker;
  bool tracking = false;
  String? error;
  int? stationCount;
  String? info;
  List<Map<String, dynamic>> journeys = [];
  String modeLabel = 'ukendt';
  bool _autoNavigated = false;
  final List<Map<String, dynamic>> localEvents = [];
  final List<String> _nudgeMessages = [];
  final List<Timer> _nudgeTimers = [];
  List<Map<String, dynamic>> backendEvents = [];
  bool loadingEvents = false;
  NotificationsService? noti;
  DateTime? trackingStartedAt;
  bool uploadingTicket = false;

  @override
  void initState() {
    super.initState();
    api = ApiClient(baseUrl: apiBaseUrl);
    noti = NotificationsService();
    noti?.init();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    try {
      final devSvc = DeviceService(api);
      final id = await devSvc.ensureRegistered();
      // Fetch stations once for geofencing seeds
      int? count;
      try {
        final stationsSvc = StationsService(baseUrl: api.baseUrl);
        final stations = await stationsSvc.fetchStations();
        count = stations.length;
      } catch (_) {
        // ignore stations errors for now
      }
      setState(() {
        deviceId = id;
        tracker = ShadowTracker(api: api, deviceId: id);
        error = null;
        stationCount = count;
      });
      // Fetch journeys once registered
      await _refreshJourneys();
      await _refreshEvents();
      _updateMode();
    } catch (e) {
      // Keep UI alive even if backend is unreachable.
      setState(() {
        error = 'Kunne ikke naa backend: $e';
      });
    }
  }

  Future<void> _toggleTracking() async {
    if (tracker == null) return;
    if (tracking) {
      await tracker!.stop();
      _cancelNudges();
      trackingStartedAt = null;
    } else {
      await tracker!.start();
      trackingStartedAt = DateTime.now();
      _scheduleNudges();
    }
    setState(() {
      tracking = !tracking;
    });
  }

  Future<void> _postEvent(String type, Map<String, dynamic> payload) async {
    try {
      await api.post('/api/events', {
        'device_id': deviceId,
        'type': type,
        'payload': payload,
      });
      localEvents.add({
        'ts': DateTime.now().toIso8601String(),
        'type': type,
        'payload': payload,
      });
      setState(() {
        info = 'Event sent: $type';
      });
    } catch (e) {
      setState(() {
        error = 'Event error: $e';
      });
    }
  }

  Future<void> _uploadTicket(ImageSource source) async {
    if (deviceId == null || uploadingTicket) return;
    final picker = ImagePicker();
    final XFile? file = await picker.pickImage(source: source, imageQuality: 85);
    if (file == null) return;
    setState(() {
      uploadingTicket = true;
      info = 'Uploader billet...';
      error = null;
    });
    try {
      final uri = Uri.parse('${api.baseUrl}/api/tickets/match');
      final req = http.MultipartRequest('POST', uri);
      req.fields['device_id'] = deviceId!;
      final bytes = await file.readAsBytes();
      final filename = file.name.isNotEmpty ? file.name : 'ticket.jpg';
      req.files.add(http.MultipartFile.fromBytes(
        'image',
        bytes,
        filename: filename,
        contentType: MediaType('image', 'jpeg'),
      ));
      final streamed = await req.send();
      final res = await http.Response.fromStream(streamed);
      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('API ${res.statusCode}: ${res.body}');
      }
      final json = jsonDecode(res.body);
      setState(() {
        info = 'Billet uploadet: ${json['status'] ?? 'ok'}';
      });
      await _refreshJourneys();
    } catch (e) {
      setState(() {
        error = 'Billet-upload fejlede: $e';
      });
    } finally {
      setState(() {
        uploadingTicket = false;
      });
    }
  }

  Future<void> _promptExpense(String kind) async {
    final amountCtrl = TextEditingController();
    final currencyCtrl = TextEditingController(text: 'DKK');
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text('Self-paid $kind'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: amountCtrl, decoration: const InputDecoration(labelText: 'Amount'), keyboardType: TextInputType.number),
              TextField(controller: currencyCtrl, decoration: const InputDecoration(labelText: 'Currency')),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Send')),
          ],
        );
      },
    );
    if (ok == true) {
      final amount = double.tryParse(amountCtrl.text.trim());
      final currency = currencyCtrl.text.trim();
      await _postEvent('expense_$kind', {
        'amount': amount,
        'currency': currency,
      });
    }
  }

  Future<void> _refreshJourneys() async {
    if (deviceId == null) return;
    try {
      final svc = JourneysService(baseUrl: api.baseUrl);
      final list = await svc.list(deviceId!);
      setState(() {
        journeys = list;
      });
      _updateMode();
    } catch (e) {
      // ignore for now
    }
  }

  Future<void> _refreshEvents() async {
    if (deviceId == null) return;
    setState(() {
      loadingEvents = true;
    });
    try {
      final svc = EventsService(baseUrl: api.baseUrl);
      final list = await svc.list(deviceId: deviceId, limit: 20);
      setState(() {
        backendEvents = list;
      });
    } catch (_) {
      // ignore
    } finally {
      setState(() {
        loadingEvents = false;
      });
    }
  }

  Future<void> _confirmJourney(String id) async {
    try {
      final svc = JourneysService(baseUrl: api.baseUrl);
      await svc.confirm(id);
      setState(() {
        info = 'Confirmed journey $id';
      });
    } catch (e) {
      setState(() {
        error = 'Confirm error: $e';
      });
    }
  }

  void _updateMode() {
    // simple heuristic: if any journey has status 'ended', set ended, else in_progress if any journey exists
    String nextMode = 'in_progress';
    for (final j in journeys) {
      final status = (j['status'] ?? '').toString().toLowerCase();
      if (status == 'ended') {
        nextMode = 'ended';
        break;
      }
    }
    if (journeys.isEmpty) {
      nextMode = tracking ? 'in_progress' : 'ukendt';
    }
    setState(() {
      modeLabel = nextMode;
    });
    _maybeAutoNavigate(nextMode);
  }

  void _maybeAutoNavigate(String mode) {
    if (!mounted) return;
    if (mode != 'ended') return;
    if (_autoNavigated) return;
    final endedJourneys = journeys.where((j) {
      final status = (j['status'] ?? '').toString().toLowerCase();
      return status == 'ended';
    }).toList();
    if (endedJourneys.isEmpty) return;
    _autoNavigated = true;
    final first = endedJourneys.first;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CaseCloseScreen(journey: first),
        ),
      );
    });
  }

  void _scheduleNudges() {
    _cancelNudges();
    // Demo durations: use minutes in prod (60/90/100)
    final entries = [
      const Duration(minutes: 1),
      const Duration(minutes: 2),
      const Duration(minutes: 3),
    ];
    for (final d in entries) {
      _nudgeTimers.add(Timer(d, () {
        _addNudge('Reminder efter ${d.inMinutes} min: tjek forsinkelse/assistance.');
      }));
    }
  }

  void _cancelNudges() {
    for (final t in _nudgeTimers) {
      t.cancel();
    }
    _nudgeTimers.clear();
  }

  void _addNudge(String msg) {
    setState(() {
      _nudgeMessages.add(msg);
    });
    noti?.showNow('Live Assist', msg);
  }

  @override
  Widget build(BuildContext context) {
    final List<String> nudges = [];
    if (tracking && modeLabel == 'in_progress') {
      nudges.add('Tracking aktiv - pings sendes til backend.');
    }
    if (modeLabel == 'ended') {
      nudges.add('Rejsen er afsluttet - udfyld Case Close.');
    }
    nudges.addAll(_nudgeMessages.take(3));

    return Scaffold(
      appBar: AppBar(title: const Text('Live Assist')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Device: ${deviceId ?? 'registering...'}'),
            if (stationCount != null) ...[
              const SizedBox(height: 4),
              Text('Stations loaded: $stationCount'),
            ],
            if (info != null) ...[
              const SizedBox(height: 4),
              Text(info!, style: const TextStyle(color: Colors.green)),
            ],
            const SizedBox(height: 4),
            Row(
              children: [
                const Text('Mode: '),
                Text(modeLabel, style: TextStyle(color: modeLabel == 'ended' ? Colors.red : Colors.blue)),
              ],
            ),
            if (nudges.isNotEmpty) ...[
              const SizedBox(height: 8),
              ...nudges.map((n) => Card(
                    color: Colors.amber.shade50,
                    child: Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: Row(
                        children: [
                          const Icon(Icons.info, color: Colors.orange),
                          const SizedBox(width: 8),
                          Expanded(child: Text(n)),
                        ],
                      ),
                    ),
                  )),
            ],
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                ElevatedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('SOS (stub)')));
                  },
                  icon: const Icon(Icons.warning),
                  label: const Text('SOS'),
                ),
                OutlinedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Chat/support (stub)')));
                  },
                  icon: const Icon(Icons.chat),
                  label: const Text('Support'),
                ),
              ],
            ),
            if (error != null) ...[
              const SizedBox(height: 8),
              Text(
                error!,
                style: const TextStyle(color: Colors.red),
              ),
            ],
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: deviceId == null ? null : _toggleTracking,
              child: Text(tracking ? 'Stop tracking' : 'Start tracking'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: deviceId == null
                  ? null
                  : () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => JourneysListScreen(deviceId: deviceId!),
                        ),
                      );
                    },
              child: const Text('Se rejser / Case Close'),
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Hændelseslog'),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: deviceId == null ? null : _refreshEvents,
                ),
              ],
            ),
            if (loadingEvents) const LinearProgressIndicator(),
            ...backendEvents.map((e) {
              final ts = e['received_at'] ?? e['timestamp'] ?? e['ts'] ?? '';
              final type = e['type'] ?? '';
              return ListTile(
                leading: const Icon(Icons.history),
                title: Text(type.toString()),
                subtitle: Text(ts.toString()),
              );
            }),
            if (backendEvents.isNotEmpty) const SizedBox(height: 12),
            if (localEvents.isNotEmpty) ...[
              const Text('Seneste hændelser'),
              const SizedBox(height: 8),
              ...localEvents.reversed.take(5).map((e) {
                final ts = e['ts'] ?? '';
                final type = e['type'] ?? '';
                return ListTile(
                  leading: const Icon(Icons.timeline),
                  title: Text(type.toString()),
                  subtitle: Text(ts.toString()),
                );
              }),
              const SizedBox(height: 24),
            ],
            const Text('Offers'),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(label: const Text('Meals'), onPressed: deviceId == null ? null : () => _postEvent('offer_meals', {})),
                ActionChip(label: const Text('Hotel'), onPressed: deviceId == null ? null : () => _postEvent('offer_hotel', {})),
                ActionChip(label: const Text('Transport to destination'), onPressed: deviceId == null ? null : () => _postEvent('offer_transport_destination', {})),
                ActionChip(label: const Text('Transport away from train'), onPressed: deviceId == null ? null : () => _postEvent('offer_transport_away', {})),
              ],
            ),
            const SizedBox(height: 16),
            const Text('Self-paid expense'),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(label: const Text('Taxi'), onPressed: deviceId == null ? null : () => _promptExpense('taxi')),
                ActionChip(label: const Text('Bus'), onPressed: deviceId == null ? null : () => _promptExpense('bus')),
                ActionChip(label: const Text('Hotel'), onPressed: deviceId == null ? null : () => _promptExpense('hotel')),
                ActionChip(label: const Text('Meals'), onPressed: deviceId == null ? null : () => _promptExpense('meals')),
              ],
            ),
            const SizedBox(height: 16),
            const Text('Billet'),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(
                  label: const Text('Foto billet'),
                  onPressed: (deviceId == null || uploadingTicket) ? null : () => _uploadTicket(ImageSource.camera),
                ),
                ActionChip(
                  label: const Text('Galleri/fil'),
                  onPressed: (deviceId == null || uploadingTicket) ? null : () => _uploadTicket(ImageSource.gallery),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text('Status'),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(label: const Text('Stranded'), onPressed: deviceId == null ? null : () => _postEvent('status_stranded', {})),
                ActionChip(label: const Text('Cancelled'), onPressed: deviceId == null ? null : () => _postEvent('status_cancelled', {})),
                ActionChip(label: const Text('New departure time'), onPressed: deviceId == null ? null : () => _postEvent('status_new_departure', {})),
              ],
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Detected journeys'),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: deviceId == null ? null : _refreshJourneys,
                ),
              ],
            ),
            ...journeys.map((j) {
              final id = (j['id'] ?? '').toString();
              final start = (j['start'] ?? '').toString();
              final end = (j['end'] ?? '').toString();
              return Card(
                child: ListTile(
                  title: Text('Journey $id'),
                  subtitle: Text('$start -> $end (${j['count'] ?? ''} pings)'),
                  trailing: TextButton(
                    onPressed: deviceId == null ? null : () => _confirmJourney(id),
                    child: const Text('Confirm'),
                  ),
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}
