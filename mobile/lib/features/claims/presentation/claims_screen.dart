import 'package:flutter/material.dart';

class ClaimsScreen extends StatelessWidget {
  final List<Map<String, dynamic>> journeys;
  final bool commuterMode;
  final VoidCallback onRefresh;
  final ValueChanged<Map<String, dynamic>> onOpenJourney;

  const ClaimsScreen({
    super.key,
    required this.journeys,
    required this.commuterMode,
    required this.onRefresh,
    required this.onOpenJourney,
  });

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Map<String, dynamic>>>{
      'Klar til review': [],
      'Indsendt / i gang': [],
      'Afsluttet': [],
    };

    for (final journey in journeys) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
      if (['ended', 'review', 'ready'].contains(status)) {
        groups['Klar til review']!.add(journey);
      } else if ([
        'submitted',
        'sent',
        'waiting',
        'open',
        'detected',
        'in_progress',
        'active',
      ].contains(status)) {
        groups['Indsendt / i gang']!.add(journey);
      } else {
        groups['Afsluttet']!.add(journey);
      }
    }

    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            commuterMode ? 'Pendler-claims' : 'Claims',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(
            commuterMode
                ? 'Claims vises som data-pack, claim-assist og senere payout-status.'
                : 'Her samles drafts, indsendte sager og afsluttede resultater.',
          ),
          const SizedBox(height: 16),
          for (final entry in groups.entries) ...[
            Text(entry.key, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (entry.value.isEmpty)
              Card(
                child: ListTile(
                  leading: const Icon(Icons.inbox_outlined),
                  title: Text('Ingen sager i "${entry.key}"'),
                ),
              )
            else
              ...entry.value.map((journey) {
                final dep = (journey['dep_station'] ?? journey['start'] ?? '')
                    .toString();
                final arr = (journey['arr_station'] ?? journey['end'] ?? '')
                    .toString();
                final delay =
                    (journey['delay_minutes'] ?? journey['delay'] ?? '')
                        .toString();
                final status = (journey['status'] ?? '').toString();
                return Card(
                  child: ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text('$dep -> $arr'),
                    subtitle: Text('Status: $status • Delay: $delay min'),
                    trailing: Text(commuterMode ? 'Claim assist' : 'Open'),
                    onTap: () => onOpenJourney(journey),
                  ),
                );
              }),
            const SizedBox(height: 16),
          ],
        ],
      ),
    );
  }
}
