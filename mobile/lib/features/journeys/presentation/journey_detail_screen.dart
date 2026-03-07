import 'package:flutter/material.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/claims/presentation/claim_review_screen.dart';
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

  String _stringValue(List<String> keys) {
    for (final key in keys) {
      final value = widget.journey[key];
      if (value != null && value.toString().trim().isNotEmpty) {
        return value.toString();
      }
    }
    return '';
  }

  int? _intValue(List<String> keys) {
    for (final key in keys) {
      final value = widget.journey[key];
      if (value == null) {
        continue;
      }
      if (value is int) {
        return value;
      }
      final parsed = int.tryParse(value.toString());
      if (parsed != null) {
        return parsed;
      }
    }
    return null;
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    final deviceId = _stringValue(['device_id']);
    try {
      final list = await eventsService.list(
        deviceId: deviceId.isEmpty ? null : deviceId,
        limit: 50,
      );
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

  void _openClaimReview() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ClaimReviewScreen(journey: widget.journey),
      ),
    );
  }

  void _openChatHint() {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Text(
                'Brug chatten selektivt',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              SizedBox(height: 8),
              Text(
                'Trip detail skal primært give overblik. Chatten skal kun bruges til manglende svar, forklaring eller dokumentation.',
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final dep = _stringValue(['dep_station', 'start']);
    final arr = _stringValue(['arr_station', 'end']);
    final delay = _intValue(['delay_minutes', 'delay']);
    final status = _stringValue(['status']).toLowerCase();
    final deviceId = _stringValue(['device_id']);
    final routeLabel = dep.isEmpty && arr.isEmpty
        ? 'Ukendt rejse'
        : '$dep -> $arr';
    final statusText = _statusText(status);
    final nextAction = _nextActionText(status);
    final canShowReroute = deviceId.isNotEmpty && arr.isNotEmpty;

    return Scaffold(
      appBar: AppBar(title: const Text('Trip detail')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      routeLabel,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _StatusChip(
                          label: statusText,
                          color: _statusColor(status),
                        ),
                        if (delay != null)
                          _StatusChip(
                            label: '$delay min',
                            color: Colors.orange.shade700,
                          ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      nextAction,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      status == 'ended' ||
                              status == 'review' ||
                              status == 'ready'
                          ? 'Rejsen ligner en sag, der skal gennemgås kort og derefter sendes videre.'
                          : 'Hold denne side kort. Brug den til overblik og gå kun videre til næste relevante handling.',
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Hurtige handlinger',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                _ActionTile(
                  title: 'Review claim',
                  subtitle: 'Kort overblik og adgang til avanceret review.',
                  icon: Icons.assignment_outlined,
                  onTap: _openClaimReview,
                ),
                _ActionTile(
                  title: 'Åbn chat',
                  subtitle:
                      'Brug kun chatten til forklaring eller manglende oplysninger.',
                  icon: Icons.chat_bubble_outline,
                  onTap: _openChatHint,
                ),
                if (canShowReroute)
                  _ActionTile(
                    title: 'Alternativ rute',
                    subtitle:
                        'Se forslag til videre transport mod destinationen.',
                    icon: Icons.alt_route,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => RerouteScreen(
                            destination: arr,
                            deviceId: deviceId,
                          ),
                        ),
                      );
                    },
                  ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              'Rejseoplysninger',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Card(
              child: Column(
                children: [
                  _FactTile(label: 'Fra', value: dep),
                  _FactTile(label: 'Til', value: arr),
                  _FactTile(
                    label: 'Afgang',
                    value: _stringValue(['dep_time', 'start']),
                  ),
                  _FactTile(
                    label: 'Ankomst',
                    value: _stringValue(['arr_time', 'end']),
                  ),
                  _FactTile(
                    label: 'Billettype',
                    value: _stringValue(['ticket_type']),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Aktivitetslog',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Card(
              child: loading
                  ? const Padding(
                      padding: EdgeInsets.all(24),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  : error != null
                  ? ListTile(
                      leading: const Icon(Icons.error_outline),
                      title: const Text('Kunne ikke hente aktivitetslog'),
                      subtitle: Text(error!),
                    )
                  : events.isEmpty
                  ? const ListTile(
                      leading: Icon(Icons.timeline),
                      title: Text('Ingen events endnu'),
                      subtitle: Text(
                        'Det er ok. Produktet skal stadig kunne fungere uden timeline-data.',
                      ),
                    )
                  : Column(
                      children: [
                        for (final event in events.take(8))
                          ListTile(
                            leading: const Icon(Icons.timeline),
                            title: Text((event['type'] ?? 'event').toString()),
                            subtitle: Text(
                              [
                                (event['timestamp'] ?? '').toString(),
                                (event['description'] ?? '').toString(),
                              ].where((part) => part.isNotEmpty).join('\n'),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  String _statusText(String status) {
    switch (status) {
      case 'ended':
      case 'review':
      case 'ready':
        return 'Klar til review';
      case 'active':
      case 'in_progress':
      case 'detected':
        return 'Rejse i gang';
      case 'sent':
      case 'submitted':
        return 'Indsendt';
      case 'paid':
        return 'Afsluttet';
      default:
        return status.isEmpty ? 'Ukendt status' : status;
    }
  }

  String _nextActionText(String status) {
    if (['ended', 'review', 'ready'].contains(status)) {
      return 'Næste trin: review og claim';
    }
    if (['active', 'in_progress', 'detected'].contains(status)) {
      return 'Næste trin: hold styr på hændelsen';
    }
    if (['sent', 'submitted'].contains(status)) {
      return 'Næste trin: afvent svar eller tilføj dokumentation';
    }
    return 'Næste trin: gennemgå rejseoplysninger';
  }

  Color _statusColor(String status) {
    if (['ended', 'review', 'ready'].contains(status)) {
      return Colors.orange.shade700;
    }
    if (['active', 'in_progress', 'detected'].contains(status)) {
      return Colors.blue.shade700;
    }
    if (['sent', 'submitted'].contains(status)) {
      return Colors.purple.shade700;
    }
    return Colors.grey.shade700;
  }
}

class _FactTile extends StatelessWidget {
  final String label;
  final String value;

  const _FactTile({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      title: Text(label),
      subtitle: Text(value.isEmpty ? 'Ikke registreret endnu' : value),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusChip({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text(label),
      side: BorderSide(color: color.withValues(alpha: 0.25)),
      backgroundColor: color.withValues(alpha: 0.12),
      labelStyle: TextStyle(color: color),
      visualDensity: VisualDensity.compact,
    );
  }
}

class _ActionTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;

  const _ActionTile({
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
