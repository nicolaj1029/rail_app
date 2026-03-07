import 'package:flutter/material.dart';

import 'package:mobile/features/profile/data/commuter_profile_store.dart';

class HomeScreen extends StatelessWidget {
  final bool loading;
  final String? error;
  final String? deviceId;
  final List<Map<String, dynamic>> journeys;
  final Map<String, dynamic> homeSummary;
  final CommuterProfile commuterProfile;
  final VoidCallback onRefresh;
  final ValueChanged<int> onNavigate;
  final VoidCallback onOpenLiveAssist;
  final VoidCallback? onOpenPrimaryReview;

  const HomeScreen({
    super.key,
    required this.loading,
    required this.error,
    required this.deviceId,
    required this.journeys,
    required this.homeSummary,
    required this.commuterProfile,
    required this.onRefresh,
    required this.onNavigate,
    required this.onOpenLiveAssist,
    required this.onOpenPrimaryReview,
  });

  int get readyCount => _summaryInt('ready_count');
  int get inProgressCount => _summaryInt('active_count');
  int get submittedCount => _summaryInt('submitted_count');

  int _summaryInt(String key) {
    final summary =
        (homeSummary['summary'] as Map?)?.cast<String, dynamic>() ??
        const <String, dynamic>{};
    final value = summary[key];
    if (value is int) {
      return value;
    }
    return int.tryParse('${value ?? ''}') ?? 0;
  }

  List<Map<String, dynamic>> get nextActions =>
      (homeSummary['next_actions'] as List?)?.cast<Map<String, dynamic>>() ??
      const <Map<String, dynamic>>[];

  void _handleActionTap(Map<String, dynamic> action) {
    final kind = (action['kind'] ?? '').toString();
    switch (kind) {
      case 'review_journey':
        if (onOpenPrimaryReview != null) {
          onOpenPrimaryReview!();
          return;
        }
        onNavigate(1);
        return;
      case 'open_claims':
        onNavigate(2);
        return;
      case 'continue_live':
        onOpenLiveAssist();
        return;
      default:
        onNavigate(1);
    }
  }

  @override
  Widget build(BuildContext context) {
    final commuterMode =
        commuterProfile.enabled && commuterProfile.isConfigured;
    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            commuterMode ? 'Pendler-overblik' : 'Rail claims',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(
            commuterMode
                ? 'Appen prioriterer dine faste rejser, hurtig review og data-pack.'
                : 'Start en sag, upload en billet eller fortsæt en detekteret rejse.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          if (error != null)
            Card(
              color: Colors.red.shade50,
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: const Text('Backend-forbindelse mangler'),
                subtitle: Text(error!),
                trailing: TextButton(
                  onPressed: onRefresh,
                  child: const Text('Prøv igen'),
                ),
              ),
            ),
          if (loading)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
            )
          else ...[
            Row(
              children: [
                Expanded(
                  child: _MetricCard(
                    label: commuterMode
                        ? 'Mulige forsinkelser'
                        : 'Klar til review',
                    value: readyCount.toString(),
                    icon: Icons.report_problem_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _MetricCard(
                    label: 'Aktive rejser',
                    value: inProgressCount.toString(),
                    icon: Icons.train_outlined,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _MetricCard(
                    label: 'Indsendte sager',
                    value: submittedCount.toString(),
                    icon: Icons.description_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _MetricCard(
                    label: 'Device',
                    value: deviceId ?? 'ikke registreret',
                    icon: Icons.phone_android_outlined,
                  ),
                ),
              ],
            ),
          ],
          if (nextActions.isNotEmpty) ...[
            const SizedBox(height: 20),
            Text(
              'Næste handling',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            ...nextActions.map(
              (action) => Card(
                child: ListTile(
                  leading: Icon(_actionIcon((action['kind'] ?? '').toString())),
                  title: Text((action['title'] ?? '').toString()),
                  subtitle: Text((action['subtitle'] ?? '').toString()),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _handleActionTap(action),
                ),
              ),
            ),
          ],
          const SizedBox(height: 20),
          Text(
            commuterMode ? 'Hurtige handlinger' : 'Kom i gang',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _ActionCard(
                title: commuterMode ? 'Start live hjælp' : 'Jeg er på rejse nu',
                subtitle: commuterMode
                    ? 'Log udgifter og hjælp under den aktuelle rejse.'
                    : 'Åbn live assist til problemer under rejsen.',
                icon: Icons.play_circle_outline,
                onTap: onOpenLiveAssist,
              ),
              _ActionCard(
                title: commuterMode ? 'Dagens rejser' : 'Se rejser',
                subtitle: commuterMode
                    ? 'Review faste rejser og mulige forsinkelser.'
                    : 'Åbn registrerede eller detekterede rejser.',
                icon: Icons.timeline,
                onTap: () => onNavigate(1),
              ),
              _ActionCard(
                title: commuterMode ? 'Claim-status' : 'Claims',
                subtitle: commuterMode
                    ? 'Se data-pack, claim-assist og payout-status.'
                    : 'Se draft, submitted og paid sager.',
                icon: Icons.description_outlined,
                onTap: () => onNavigate(2),
              ),
              _ActionCard(
                title: commuterMode ? 'Chat om en sag' : 'Få hjælp i chat',
                subtitle: 'Åbn kontekstuel chat med quick replies.',
                icon: Icons.chat_bubble_outline,
                onTap: () => onNavigate(3),
              ),
              _ActionCard(
                title: commuterMode
                    ? 'Pendler-opsætning'
                    : 'Profil og opsætning',
                subtitle: commuterMode
                    ? '${commuterProfile.operatorName} • ${commuterProfile.productName}'
                    : 'Opsæt pendlerprodukt, tracking og betalingsinfo.',
                icon: Icons.person_outline,
                onTap: () => onNavigate(4),
              ),
            ],
          ),
          if (onOpenPrimaryReview != null && readyCount > 0) ...[
            const SizedBox(height: 20),
            Card(
              child: ListTile(
                leading: const Icon(Icons.assignment_turned_in_outlined),
                title: Text(
                  commuterMode
                      ? 'Der er $readyCount rejser klar til hurtigt review'
                      : 'Der er $readyCount sager klar til review',
                ),
                subtitle: const Text(
                  'Åbn den vigtigste sag direkte fra forsiden.',
                ),
                trailing: FilledButton(
                  onPressed: onOpenPrimaryReview,
                  child: const Text('Review nu'),
                ),
              ),
            ),
          ],
          const SizedBox(height: 20),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    commuterMode ? 'Pendler-mode aktiv' : 'Standard-mode aktiv',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    commuterMode
                        ? 'Din profil er sat op til faste ruter. Appen kan derfor fokusere på hurtig bekræftelse, seasonpass og claim-assist.'
                        : 'Ingen pendlerprofil er aktiveret endnu. Appen viser derfor standard-flow med rejser, upload og claims.',
                  ),
                  const SizedBox(height: 12),
                  TextButton.icon(
                    onPressed: () => onNavigate(4),
                    icon: const Icon(Icons.tune),
                    label: Text(
                      commuterMode
                          ? 'Ret pendler-opsætning'
                          : 'Opsæt pendlerprofil',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _actionIcon(String kind) {
    switch (kind) {
      case 'review_journey':
        return Icons.assignment_outlined;
      case 'open_claims':
        return Icons.description_outlined;
      case 'continue_live':
        return Icons.play_circle_outline;
      default:
        return Icons.chevron_right;
    }
  }
}

class _MetricCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;

  const _MetricCard({
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

class _ActionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;

  const _ActionCard({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 280,
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
