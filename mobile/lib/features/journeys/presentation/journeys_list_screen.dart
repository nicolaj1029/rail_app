import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/chat/data/chat_context.dart';
import 'package:mobile/features/chat/presentation/chat_screen.dart';
import 'package:mobile/features/claims/presentation/claim_review_screen.dart';
import 'package:mobile/features/journeys/data/journeys_service.dart';
import 'package:mobile/features/journeys/presentation/journey_detail_screen.dart';
import 'package:mobile/features/journeys/presentation/manual_journey_screen.dart';
import 'package:mobile/features/profile/data/commuter_profile_store.dart';
import 'package:mobile/shared/services/tickets_service.dart';

class JourneysListScreen extends StatefulWidget {
  final String deviceId;
  final bool embedded;

  const JourneysListScreen({
    super.key,
    required this.deviceId,
    this.embedded = false,
  });

  @override
  State<JourneysListScreen> createState() => _JourneysListScreenState();
}

class _JourneysListScreenState extends State<JourneysListScreen> {
  late final JourneysService _service;
  late final TicketsService _tickets;

  bool loading = true;
  bool matching = false;
  String? error;
  List<Map<String, dynamic>> journeys = [];

  @override
  void initState() {
    super.initState();
    _service = JourneysService(baseUrl: apiBaseUrl);
    _tickets = TicketsService(baseUrl: apiBaseUrl);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final list = await _service.list(widget.deviceId);
      if (!mounted) {
        return;
      }
      setState(() {
        journeys = list;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
        });
      }
    }
  }

  void _openClaimReview(Map<String, dynamic> journey) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ClaimReviewScreen(journey: journey)),
    );
  }

  void _openJourneyChat(Map<String, dynamic> journey) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChatScreen(
          commuterMode: false,
          deviceId: widget.deviceId,
          commuterProfile: CommuterProfile.empty(),
          initialContext: ChatContext.fromJourney(
            journey,
            source: 'journeys_list',
          ),
        ),
      ),
    );
  }

  Future<void> _openManual() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ManualJourneyScreen(deviceId: widget.deviceId),
      ),
    );
    await _load();
  }

  Future<void> _matchTicket() async {
    if (matching) {
      return;
    }

    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (file == null) {
      return;
    }

    setState(() {
      matching = true;
      error = null;
    });

    try {
      final res = await _tickets.matchTicket(
        deviceId: widget.deviceId,
        file: file,
      );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Billet uploadet: ${res['status'] ?? 'ok'}')),
      );
      await _load();
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          matching = false;
        });
      }
    }
  }

  String _statusOf(Map<String, dynamic> journey) =>
      (journey['status'] ?? '').toString().toLowerCase();

  Map<String, List<Map<String, dynamic>>> _groupJourneys() {
    final groups = <String, List<Map<String, dynamic>>>{
      'Needs review': [],
      'Active now': [],
      'Other journeys': [],
    };

    for (final journey in journeys) {
      final status = _statusOf(journey);
      if (['ended', 'review', 'ready'].contains(status)) {
        groups['Needs review']!.add(journey);
      } else if (['active', 'in_progress', 'detected'].contains(status)) {
        groups['Active now']!.add(journey);
      } else {
        groups['Other journeys']!.add(journey);
      }
    }

    return groups;
  }

  int get _reviewCount => _groupJourneys()['Needs review']!.length;
  int get _activeCount => _groupJourneys()['Active now']!.length;

  @override
  Widget build(BuildContext context) {
    final groups = _groupJourneys();

    final content = Padding(
      padding: const EdgeInsets.all(12),
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
          ? Center(child: Text('Error: $error'))
          : journeys.isEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Vi fandt ingen rejser',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Opret en manuel rejse eller upload en billet, så forsøger vi at matche den.',
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          children: [
                            ElevatedButton.icon(
                              onPressed: _openManual,
                              icon: const Icon(Icons.add),
                              label: const Text('Anmeld rejse manuelt'),
                            ),
                            OutlinedButton.icon(
                              onPressed: matching ? null : _matchTicket,
                              icon: const Icon(Icons.upload),
                              label: Text(
                                matching ? 'Uploader...' : 'Upload billet',
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            )
          : ListView(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _TripsMetricCard(
                        label: 'Needs review',
                        value: _reviewCount.toString(),
                        icon: Icons.assignment_outlined,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _TripsMetricCard(
                        label: 'Active now',
                        value: _activeCount.toString(),
                        icon: Icons.play_circle_outline,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                for (final entry in groups.entries) ...[
                  if (entry.value.isNotEmpty) ...[
                    Text(
                      entry.key,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    ...entry.value.map(_buildJourneyCard),
                    const SizedBox(height: 16),
                  ],
                ],
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        ElevatedButton.icon(
                          onPressed: _openManual,
                          icon: const Icon(Icons.add),
                          label: const Text('Anmeld rejse manuelt'),
                        ),
                        OutlinedButton.icon(
                          onPressed: matching ? null : _matchTicket,
                          icon: const Icon(Icons.upload),
                          label: Text(
                            matching ? 'Uploader...' : 'Upload billet',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );

    if (widget.embedded) {
      return content;
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Journeys'),
        actions: [
          IconButton(
            onPressed: loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: content,
    );
  }

  Widget _buildJourneyCard(Map<String, dynamic> journey) {
    final dep = (journey['dep_station'] ?? journey['start'] ?? '').toString();
    final arr = (journey['arr_station'] ?? journey['end'] ?? '').toString();
    final routeLabel = (journey['route_label'] ?? '').toString();
    final delay = (journey['delay_minutes'] ?? journey['delay'] ?? '')
        .toString();
    final status = (journey['status'] ?? '').toString();
    final normalizedStatus = status.toLowerCase();
    final readyForReview = [
      'ended',
      'review',
      'ready',
    ].contains(normalizedStatus);
    final activeNow = [
      'active',
      'in_progress',
      'detected',
    ].contains(normalizedStatus);
    final statusColor = readyForReview
        ? Colors.orange
        : activeNow
        ? Colors.green
        : Colors.blue;

    return Card(
      child: ListTile(
        title: Text(
          routeLabel.isNotEmpty
              ? routeLabel
              : (dep.isEmpty && arr.isEmpty ? 'Ukendt rejse' : '$dep -> $arr'),
        ),
        subtitle: Text(
          delay.isEmpty
              ? 'Status: $status'
              : 'Forsinkelse: $delay min | Status: $status',
        ),
        trailing: SizedBox(
          width: 180,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(status, style: TextStyle(color: statusColor)),
              ),
              const SizedBox(height: 6),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    tooltip: 'Chat om denne rejse',
                    visualDensity: VisualDensity.compact,
                    onPressed: () => _openJourneyChat(journey),
                    icon: const Icon(Icons.chat_bubble_outline),
                  ),
                  TextButton(
                    onPressed: () => _openClaimReview(journey),
                    child: Text(readyForReview ? 'Review' : 'Open'),
                  ),
                ],
              ),
            ],
          ),
        ),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => JourneyDetailScreen(journey: journey),
            ),
          );
        },
      ),
    );
  }
}

class _TripsMetricCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;

  const _TripsMetricCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(icon, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 4),
                  Text(value, style: Theme.of(context).textTheme.titleLarge),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
