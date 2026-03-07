import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/claims/presentation/claim_review_screen.dart';
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
      int? count;
      try {
        final stationsSvc = StationsService(baseUrl: api.baseUrl);
        final stations = await stationsSvc.fetchStations();
        count = stations.length;
      } catch (_) {}
      setState(() {
        deviceId = id;
        tracker = ShadowTracker(api: api, deviceId: id);
        error = null;
        stationCount = count;
      });
      await _refreshJourneys();
      await _refreshEvents();
      _updateMode();
    } catch (e) {
      setState(() {
        error = 'Kunne ikke nå backend: $e';
      });
    }
  }

  Future<void> _toggleTracking() async {
    if (tracker == null) {
      return;
    }
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
        info = 'Event sendt: $type';
      });
    } catch (e) {
      setState(() {
        error = 'Event-fejl: $e';
      });
    }
  }

  Future<void> _uploadTicket(ImageSource source) async {
    if (deviceId == null || uploadingTicket) {
      return;
    }
    final picker = ImagePicker();
    final file = await picker.pickImage(source: source, imageQuality: 85);
    if (file == null) {
      return;
    }
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
      req.files.add(
        http.MultipartFile.fromBytes(
          'image',
          bytes,
          filename: filename,
          contentType: MediaType('image', 'jpeg'),
        ),
      );
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
          title: Text('Selvbetalt $kind'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: amountCtrl,
                decoration: const InputDecoration(labelText: 'Beløb'),
                keyboardType: TextInputType.number,
              ),
              TextField(
                controller: currencyCtrl,
                decoration: const InputDecoration(labelText: 'Valuta'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Annuller'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Gem'),
            ),
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
    if (deviceId == null) {
      return;
    }
    try {
      final svc = JourneysService(baseUrl: api.baseUrl);
      final list = await svc.list(deviceId!);
      setState(() {
        journeys = list;
      });
      _updateMode();
    } catch (_) {}
  }

  Future<void> _refreshEvents() async {
    if (deviceId == null) {
      return;
    }
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
        info = 'Bekræftede rejse $id';
      });
    } catch (e) {
      setState(() {
        error = 'Confirm-fejl: $e';
      });
    }
  }

  void _updateMode() {
    var nextMode = 'in_progress';
    for (final journey in journeys) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
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
    if (!mounted || mode != 'ended' || _autoNavigated) {
      return;
    }
    final endedJourneys = journeys.where((journey) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
      return status == 'ended';
    }).toList();
    if (endedJourneys.isEmpty) {
      return;
    }
    _autoNavigated = true;
    final first = endedJourneys.first;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => ClaimReviewScreen(journey: first)),
      );
    });
  }

  void _scheduleNudges() {
    _cancelNudges();
    final entries = [
      const Duration(minutes: 1),
      const Duration(minutes: 2),
      const Duration(minutes: 3),
    ];
    for (final duration in entries) {
      _nudgeTimers.add(
        Timer(duration, () {
          _addNudge(
            'Påmindelse efter ${duration.inMinutes} min: tjek forsinkelse og assistance.',
          );
        }),
      );
    }
  }

  void _cancelNudges() {
    for (final timer in _nudgeTimers) {
      timer.cancel();
    }
    _nudgeTimers.clear();
  }

  void _addNudge(String message) {
    setState(() {
      _nudgeMessages.add(message);
    });
    noti?.showNow('Live Assist', message);
  }

  void _openJourneyReview(Map<String, dynamic> journey) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ClaimReviewScreen(journey: journey)),
    );
  }

  void _showTicketOptions(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.photo_camera),
                title: const Text('Foto billet'),
                onTap: () {
                  Navigator.pop(context);
                  _uploadTicket(ImageSource.camera);
                },
              ),
              ListTile(
                leading: const Icon(Icons.photo_library),
                title: const Text('Galleri eller fil'),
                onTap: () {
                  Navigator.pop(context);
                  _uploadTicket(ImageSource.gallery);
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _showOfferActions(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.restaurant_outlined),
                title: const Text('Mad eller forfriskninger'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('offer_meals', {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.hotel_outlined),
                title: const Text('Hotel eller overnatning'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('offer_hotel', {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.alt_route),
                title: const Text('Transport til destination'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('offer_transport_destination', {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.directions_walk_outlined),
                title: const Text('Transport væk fra toget'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('offer_transport_away', {});
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _showExpenseActions(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.local_taxi_outlined),
                title: const Text('Taxi'),
                onTap: () {
                  Navigator.pop(context);
                  _promptExpense('taxi');
                },
              ),
              ListTile(
                leading: const Icon(Icons.directions_bus_outlined),
                title: const Text('Bus'),
                onTap: () {
                  Navigator.pop(context);
                  _promptExpense('bus');
                },
              ),
              ListTile(
                leading: const Icon(Icons.hotel_outlined),
                title: const Text('Hotel'),
                onTap: () {
                  Navigator.pop(context);
                  _promptExpense('hotel');
                },
              ),
              ListTile(
                leading: const Icon(Icons.restaurant_outlined),
                title: const Text('Mad'),
                onTap: () {
                  Navigator.pop(context);
                  _promptExpense('meals');
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _showStatusActions(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.report_problem_outlined),
                title: const Text('Strandet'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('status_stranded', {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.cancel_outlined),
                title: const Text('Aflyst'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('status_cancelled', {});
                },
              ),
              ListTile(
                leading: const Icon(Icons.access_time_outlined),
                title: const Text('Ny afgangstid'),
                onTap: () {
                  Navigator.pop(context);
                  _postEvent('status_new_departure', {});
                },
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final nudges = <String>[];
    if (tracking && modeLabel == 'in_progress') {
      nudges.add('Tracking aktiv - pings sendes til backend.');
    }
    if (modeLabel == 'ended') {
      nudges.add('Rejsen er afsluttet - gennemgå claim og send videre.');
    }
    nudges.addAll(_nudgeMessages.take(3));

    final readyJourneys = journeys.where((journey) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
      return ['ended', 'review', 'ready'].contains(status);
    }).toList();

    final activeJourneys = journeys.where((journey) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
      return ['active', 'in_progress', 'detected'].contains(status);
    }).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('Live Assist')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Rejsehjælp undervejs',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    tracking
                        ? 'Appen holder styr på rejsen. Registrer kun tilbud, egne udgifter og status.'
                        : 'Start tracking hvis du vil registrere hændelser live. Ellers kan du stadig gå videre til review senere.',
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _StatusPill(
                        label: tracking ? 'Tracking aktiv' : 'Tracking stoppet',
                        color: tracking ? Colors.green : Colors.grey,
                      ),
                      _StatusPill(
                        label: 'Mode: $modeLabel',
                        color: modeLabel == 'ended'
                            ? Colors.orange
                            : Colors.blue,
                      ),
                      if (stationCount != null)
                        _StatusPill(
                          label: '$stationCount stationer',
                          color: Colors.indigo,
                        ),
                    ],
                  ),
                  if (deviceId != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Device: $deviceId',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ],
              ),
            ),
          ),
          if (info != null) ...[
            const SizedBox(height: 12),
            Card(
              color: Colors.green.shade50,
              child: ListTile(
                leading: const Icon(Icons.check_circle_outline),
                title: Text(info!),
              ),
            ),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            Card(
              color: Colors.red.shade50,
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: Text(error!),
              ),
            ),
          ],
          if (nudges.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...nudges.map(
              (message) => Card(
                color: Colors.amber.shade50,
                child: ListTile(
                  leading: const Icon(Icons.info_outline, color: Colors.orange),
                  title: Text(message),
                ),
              ),
            ),
          ],
          const SizedBox(height: 16),
          Text(
            'Primære handlinger',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _ActionCard(
                title: tracking ? 'Stop tracking' : 'Start tracking',
                subtitle: tracking
                    ? 'Stop live registrering af denne rejse.'
                    : 'Start live registrering af rejsen.',
                icon: tracking
                    ? Icons.pause_circle_outline
                    : Icons.play_circle_outline,
                onTap: deviceId == null ? null : _toggleTracking,
              ),
              _ActionCard(
                title: 'Se rejser',
                subtitle: 'Åbn registrerede eller detekterede rejser.',
                icon: Icons.timeline,
                onTap: deviceId == null
                    ? null
                    : () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                JourneysListScreen(deviceId: deviceId!),
                          ),
                        );
                      },
              ),
              _ActionCard(
                title: 'Upload billet',
                subtitle: uploadingTicket
                    ? 'Uploader...'
                    : 'Tilføj billet fra kamera eller galleri.',
                icon: Icons.confirmation_number_outlined,
                onTap: deviceId == null || uploadingTicket
                    ? null
                    : () => _showTicketOptions(context),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Live handlinger',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _ActionCard(
                title: 'Selskabet tilbød noget',
                subtitle: 'Mad, hotel eller transport videre.',
                icon: Icons.support_agent_outlined,
                onTap: deviceId == null
                    ? null
                    : () => _showOfferActions(context),
              ),
              _ActionCard(
                title: 'Jeg betalte selv',
                subtitle: 'Taxi, bus, hotel eller mad.',
                icon: Icons.receipt_long_outlined,
                onTap: deviceId == null
                    ? null
                    : () => _showExpenseActions(context),
              ),
              _ActionCard(
                title: 'Opdater status',
                subtitle: 'Strandet, aflyst eller ny afgang.',
                icon: Icons.report_problem_outlined,
                onTap: deviceId == null
                    ? null
                    : () => _showStatusActions(context),
              ),
            ],
          ),
          if (readyJourneys.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              'Klar til review',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            ...readyJourneys.take(3).map((journey) {
              final id = (journey['id'] ?? '').toString();
              final start = (journey['start'] ?? '').toString();
              final end = (journey['end'] ?? '').toString();
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.assignment_outlined),
                  title: Text('Rejse $id'),
                  subtitle: Text('$start -> $end'),
                  trailing: const Text('Review'),
                  onTap: () => _openJourneyReview(journey),
                ),
              );
            }),
          ],
          if (activeJourneys.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              'Aktive rejser',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            ...activeJourneys.take(3).map((journey) {
              final id = (journey['id'] ?? '').toString();
              final start = (journey['start'] ?? '').toString();
              final end = (journey['end'] ?? '').toString();
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.train_outlined),
                  title: Text('Rejse $id'),
                  subtitle: Text('$start -> $end'),
                  trailing: TextButton(
                    onPressed: deviceId == null
                        ? null
                        : () => _confirmJourney(id),
                    child: const Text('Confirm'),
                  ),
                ),
              );
            }),
          ],
          const SizedBox(height: 16),
          ExpansionTile(
            title: const Text('Aktivitetslog'),
            subtitle: const Text('Åbn kun hvis du vil se rå events'),
            children: [
              if (loadingEvents) const LinearProgressIndicator(),
              ...backendEvents.map((event) {
                final ts =
                    event['received_at'] ??
                    event['timestamp'] ??
                    event['ts'] ??
                    '';
                final type = event['type'] ?? '';
                return ListTile(
                  leading: const Icon(Icons.history),
                  title: Text(type.toString()),
                  subtitle: Text(ts.toString()),
                );
              }),
              ...localEvents.reversed.take(5).map((event) {
                final ts = event['ts'] ?? '';
                final type = event['type'] ?? '';
                return ListTile(
                  leading: const Icon(Icons.timeline),
                  title: Text(type.toString()),
                  subtitle: Text(ts.toString()),
                );
              }),
              if (backendEvents.isEmpty && localEvents.isEmpty)
                const ListTile(
                  leading: Icon(Icons.timeline),
                  title: Text('Ingen events endnu'),
                ),
            ],
          ),
          ExpansionTile(
            title: const Text('Avanceret'),
            subtitle: const Text('Rå handlinger og tekniske genveje'),
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ElevatedButton.icon(
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('SOS (stub)')),
                      );
                    },
                    icon: const Icon(Icons.warning),
                    label: const Text('SOS'),
                  ),
                  OutlinedButton.icon(
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Chat/support (stub)')),
                      );
                    },
                    icon: const Icon(Icons.chat),
                    label: const Text('Support'),
                  ),
                  TextButton.icon(
                    onPressed: deviceId == null ? null : _refreshEvents,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Opdatér events'),
                  ),
                  TextButton.icon(
                    onPressed: deviceId == null ? null : _refreshJourneys,
                    icon: const Icon(Icons.sync),
                    label: const Text('Opdatér rejser'),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusPill({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text(label),
      backgroundColor: color.withValues(alpha: 0.12),
      side: BorderSide(color: color.withValues(alpha: 0.25)),
      labelStyle: TextStyle(color: color),
      visualDensity: VisualDensity.compact,
    );
  }
}

class _ActionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;

  const _ActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 260,
      child: Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon, size: 28),
                const SizedBox(height: 12),
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 6),
                Text(subtitle),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
