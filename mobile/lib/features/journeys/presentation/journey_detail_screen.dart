import 'package:flutter/material.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/case_close/presentation/case_close_screen.dart';
import 'package:mobile/features/live_assist/presentation/reroute_screen.dart';
import 'package:mobile/services/events_service.dart';

class JourneyDetailScreen extends StatefulWidget {
  final Map<String, dynamic> journey;
  const JourneyDetailScreen({super.key, required this.journey});

  @override
  State<JourneyDetailScreen> createState() => _JourneyDetailScreenState();
}

class _JourneyDetailScreenState extends State<JourneyDetailScreen> {
  late final EventsService eventsService;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> events = [];

  @override
  void initState() {
    super.initState();
    eventsService = EventsService(baseUrl: apiBaseUrl);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    // Prefer device_id if present; else empty
    final deviceId = (widget.journey['device_id'] ?? '').toString();
    try {
      final list = await eventsService.list(deviceId: deviceId.isEmpty ? null : deviceId, limit: 50);
      setState(() {
        events = list;
      });
    } catch (e) {
      setState(() {
        error = '$e';
      });
    } finally {
      setState(() {
        loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final j = widget.journey;
    final dep = (j['dep_station'] ?? j['start'] ?? '').toString();
    final arr = (j['arr_station'] ?? j['end'] ?? '').toString();
    final delay = (j['delay_minutes'] ?? j['delay'] ?? '').toString();
    final status = (j['status'] ?? '').toString();

    return Scaffold(
      appBar: AppBar(title: const Text('Journey detail')),
      body: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Card(
              child: ListTile(
                title: Text('$dep -> $arr'),
                subtitle: Text('Delay: $delay min | Status: $status'),
                trailing: ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => CaseCloseScreen(journey: j),
                      ),
                    );
                  },
                  child: const Text('Case Close'),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                ElevatedButton.icon(
                  onPressed: () {},
                  icon: const Icon(Icons.chat),
                  label: const Text('Kontakt support (stub)'),
                ),
                const SizedBox(width: 8),
                ElevatedButton.icon(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => RerouteScreen(destination: arr, deviceId: (j['device_id'] ?? '').toString()),
                      ),
                    );
                  },
                  icon: const Icon(Icons.alt_route),
                  label: const Text('Anmod alternativ rute (stub)'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Text('Hændelseslog', style: TextStyle(fontWeight: FontWeight.bold)),
            Expanded(
              child: loading
                  ? const Center(child: CircularProgressIndicator())
                  : error != null
                      ? Center(child: Text('Fejl: $error'))
                      : ListView.builder(
                          itemCount: (events.isEmpty ? 1 : events.length),
                          itemBuilder: (context, index) {
                            if (events.isEmpty) {
                              return const ListTile(
                                leading: Icon(Icons.timeline),
                                title: Text('Ingen events endnu'),
                                subtitle: Text('Events vil blive vist her (stub).'),
                              );
                            }
                            final e = events[index];
                            final type = (e['type'] ?? '').toString();
                            final ts = (e['timestamp'] ?? '').toString();
                            final desc = (e['description'] ?? '').toString();
                            return ListTile(
                              leading: const Icon(Icons.timeline),
                              title: Text(type),
                              subtitle: Text('$ts\n$desc'),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
