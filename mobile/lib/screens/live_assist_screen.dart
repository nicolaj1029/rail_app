import 'package:flutter/material.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/device_service.dart';
import '../services/journeys_service.dart';
import '../services/shadow_tracker.dart';
import '../services/stations_service.dart';
import 'journeys_list_screen.dart';

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

  @override
  void initState() {
    super.initState();
    api = ApiClient(baseUrl: apiBaseUrl);
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
    } else {
      await tracker!.start();
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
      setState(() {
        info = 'Event sent: $type';
      });
    } catch (e) {
      setState(() {
        error = 'Event error: $e';
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
    } catch (e) {
      // ignore for now
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

  @override
  Widget build(BuildContext context) {
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
