import 'package:flutter/material.dart';

import 'package:mobile/features/case_close/presentation/case_close_screen.dart';
import 'package:mobile/features/chat/data/chat_context.dart';
import 'package:mobile/features/chat/presentation/chat_screen.dart';
import 'package:mobile/features/live_assist/presentation/reroute_screen.dart';
import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class ClaimReviewScreen extends StatelessWidget {
  final Map<String, dynamic> journey;

  const ClaimReviewScreen({super.key, required this.journey});

  String _stringValue(List<String> keys) {
    for (final key in keys) {
      final value = journey[key];
      if (value != null && value.toString().trim().isNotEmpty) {
        return value.toString();
      }
    }
    return '';
  }

  int? _intValue(List<String> keys) {
    for (final key in keys) {
      final value = journey[key];
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

  String get routeLabel {
    final provided = _stringValue(['route_label']);
    if (provided.isNotEmpty) {
      return provided;
    }
    final dep = _stringValue(['dep_station', 'start']);
    final arr = _stringValue(['arr_station', 'end']);
    if (dep.isEmpty && arr.isEmpty) {
      return 'Ukendt rute';
    }
    return '$dep -> $arr';
  }

  String get statusKey => _stringValue(['status']).toLowerCase();

  String get statusLabel {
    switch (statusKey) {
      case 'ended':
      case 'ready':
      case 'review':
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
        return statusKey.isEmpty ? 'Ukendt status' : statusKey;
    }
  }

  String get nextStepLabel {
    if (['ended', 'ready', 'review'].contains(statusKey)) {
      return 'Review og indsend';
    }
    if (['active', 'in_progress', 'detected'].contains(statusKey)) {
      return 'Fortsæt registrering';
    }
    if (['sent', 'submitted'].contains(statusKey)) {
      return 'Afvent svar';
    }
    return 'Tjek detaljer';
  }

  String get recommendedAction {
    if (['ended', 'ready', 'review'].contains(statusKey)) {
      return 'Åbn avanceret review og bekræft de sidste felter.';
    }
    if (['active', 'in_progress', 'detected'].contains(statusKey)) {
      return 'Log kun hændelser og udgifter, som ændrer udfaldet.';
    }
    if (['sent', 'submitted', 'paid'].contains(statusKey)) {
      return 'Sagen er allerede sendt. Tilføj kun mere, hvis der mangler dokumentation.';
    }
    return 'Gennemgå rejsen og vælg næste relevante handling.';
  }

  bool get isSubmitted => ['sent', 'submitted', 'paid'].contains(statusKey);

  List<String> get highlights {
    final items = <String>[];
    final delay = _intValue(['delay_minutes', 'delay']);
    if (delay != null && delay > 0) {
      items.add('Forsinkelse registreret: $delay min');
    }
    final depTime = _stringValue(['dep_time', 'start']);
    if (depTime.isNotEmpty) {
      items.add('Afgang: $depTime');
    }
    final arrTime = _stringValue(['arr_time', 'end']);
    if (arrTime.isNotEmpty) {
      items.add('Ankomst: $arrTime');
    }
    final ticketType = _stringValue(['ticket_type']);
    if (ticketType.isNotEmpty) {
      items.add('Billet: $ticketType');
    }
    if (items.isEmpty) {
      items.add('Vi mangler stadig flere rejseoplysninger.');
    }
    return items;
  }

  void _openCaseClose(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => CaseCloseScreen(journey: journey)),
    );
  }

  void _openChatHint(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChatScreen(
          commuterMode: false,
          deviceId: _stringValue(['device_id']),
          commuterProfile: CommuterProfile.empty(),
          initialContext: ChatContext.fromJourney(
            journey,
            source: 'claim_review',
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final delay = _intValue(['delay_minutes', 'delay']);
    final deviceId = _stringValue(['device_id']);
    final destination = _stringValue(['arr_station', 'end']);
    final canSuggestReroute =
        [
          'active',
          'in_progress',
          'detected',
          'ended',
          'review',
        ].contains(statusKey) &&
        destination.isNotEmpty &&
        deviceId.isNotEmpty;

    return Scaffold(
      appBar: AppBar(title: const Text('Review claim')),
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
                    routeLabel,
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _InfoChip(label: statusLabel, icon: Icons.flag_outlined),
                      if (delay != null)
                        _InfoChip(
                          label: '$delay min',
                          icon: Icons.schedule_outlined,
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    nextStepLabel,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  Text(recommendedAction),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            color: isSubmitted ? Colors.green.shade50 : Colors.blue.shade50,
            child: ListTile(
              leading: Icon(
                isSubmitted ? Icons.check_circle_outline : Icons.flag_outlined,
              ),
              title: Text(
                isSubmitted ? 'Sagen er allerede sendt' : 'Anbefalet handling',
              ),
              subtitle: Text(recommendedAction),
            ),
          ),
          const SizedBox(height: 16),
          Text('Hvad vi ved', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  for (final item in highlights)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.check_circle_outline),
                      title: Text(item),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Næste handling',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isSubmitted)
                    ElevatedButton.icon(
                      onPressed: () => _openCaseClose(context),
                      icon: const Icon(Icons.assignment_outlined),
                      label: const Text('Review og udfyld sagen'),
                    ),
                  if (!isSubmitted) const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: () => _openChatHint(context),
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: const Text('Åbn hjælp i chat'),
                  ),
                  if (canSuggestReroute) ...[
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => RerouteScreen(
                              destination: destination,
                              deviceId: deviceId,
                            ),
                          ),
                        );
                      },
                      icon: const Icon(Icons.alt_route),
                      label: const Text('Se alternativ rute'),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Produktretning',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: const [
                  Text(
                    'Mobilen skal være hurtig og enkel. Brug denne side til overblik og fortsæt kun til avanceret review, hvis der faktisk mangler juridiske eller dokumentmæssige detaljer.',
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final String label;
  final IconData icon;

  const _InfoChip({required this.label, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: Icon(icon, size: 18),
      label: Text(label),
      visualDensity: VisualDensity.compact,
    );
  }
}
