import 'package:flutter/material.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/claims/data/claims_service.dart';

class ClaimsScreen extends StatefulWidget {
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
  State<ClaimsScreen> createState() => _ClaimsScreenState();
}

class _ClaimsScreenState extends State<ClaimsScreen> {
  late final ClaimsService _service;
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _cases = [];

  @override
  void initState() {
    super.initState();
    _service = ClaimsService(baseUrl: apiBaseUrl);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final cases = await _service.list();
      if (!mounted) return;
      setState(() {
        _cases = cases;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _handleRefresh() async {
    widget.onRefresh();
    await _load();
  }

  Map<String, dynamic>? _findJourneyForCase(Map<String, dynamic> item) {
    final journeyId = (item['journey_id'] ?? '').toString();
    if (journeyId.isEmpty) {
      return null;
    }

    for (final journey in widget.journeys) {
      if ((journey['id'] ?? '').toString() == journeyId) {
        return journey;
      }
    }

    return null;
  }

  void _openCaseSummary(Map<String, dynamic> item) {
    final journey = _findJourneyForCase(item);
    if (journey != null) {
      widget.onOpenJourney(journey);
      return;
    }

    showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                (item['route_label'] ?? 'Indsendt sag').toString().isEmpty
                    ? 'Indsendt sag'
                    : (item['route_label'] ?? 'Indsendt sag').toString(),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              Text('Fil: ${(item['file'] ?? '').toString()}'),
              if ((item['submitted_at'] ?? '').toString().isNotEmpty)
                Text('Indsendt: ${(item['submitted_at'] ?? '').toString()}'),
              if ((item['ticket_type'] ?? '').toString().isNotEmpty)
                Text('Billet: ${(item['ticket_type'] ?? '').toString()}'),
              if (item['delay_minutes'] != null)
                Text('Forsinkelse: ${item['delay_minutes']} min'),
              const SizedBox(height: 12),
              const Text(
                'Denne sag findes i backend, men den tilknyttede rejse er ikke længere i den lokale liste.',
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Map<String, dynamic>>>{
      'Klar til review': [],
      'Indsendt / i gang': [],
      'Afsluttet': [],
    };

    for (final journey in widget.journeys) {
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

    for (final claim in _cases) {
      groups['Indsendt / i gang']!.add({...claim, '_source': 'case'});
    }

    return RefreshIndicator(
      onRefresh: _handleRefresh,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            widget.commuterMode ? 'Pendler-claims' : 'Claims',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text(
            widget.commuterMode
                ? 'Claims vises som data-pack, claim-assist og senere payout-status.'
                : 'Her samles review-klare rejser og indsendte sager fra backend.',
          ),
          const SizedBox(height: 12),
          if (_error != null)
            Card(
              color: Colors.red.shade50,
              child: ListTile(
                leading: const Icon(Icons.error_outline),
                title: const Text('Kunne ikke hente claims'),
                subtitle: Text(_error!),
                trailing: TextButton(
                  onPressed: _loading ? null : _handleRefresh,
                  child: const Text('Prøv igen'),
                ),
              ),
            ),
          if (_loading)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
            ),
          const SizedBox(height: 8),
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
              ...entry.value.map((item) {
                final isCase = (item['_source'] ?? '') == 'case';
                final dep = (item['dep_station'] ?? item['start'] ?? '')
                    .toString();
                final arr = (item['arr_station'] ?? item['end'] ?? '')
                    .toString();
                final routeLabel = (item['route_label'] ?? '').toString();
                final delay = (item['delay_minutes'] ?? item['delay'] ?? '')
                    .toString();
                final status = (item['status'] ?? '').toString();
                final subtitleParts = <String>[
                  if (status.isNotEmpty) 'Status: $status',
                  if (delay.isNotEmpty) 'Delay: $delay min',
                  if (isCase && (item['file'] ?? '').toString().isNotEmpty)
                    'Fil: ${(item['file'] ?? '').toString()}',
                ];

                return Card(
                  child: ListTile(
                    leading: Icon(
                      isCase
                          ? Icons.checklist_rtl_outlined
                          : Icons.description_outlined,
                    ),
                    title: Text(
                      routeLabel.isNotEmpty
                          ? routeLabel
                          : (dep.isEmpty && arr.isEmpty
                                ? 'Ukendt sag'
                                : '$dep -> $arr'),
                    ),
                    subtitle: Text(subtitleParts.join(' • ')),
                    trailing: Text(
                      isCase
                          ? 'Åbn'
                          : (widget.commuterMode ? 'Claim assist' : 'Open'),
                    ),
                    onTap: () {
                      if (isCase) {
                        _openCaseSummary(item);
                      } else {
                        widget.onOpenJourney(item);
                      }
                    },
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
