import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/claims/presentation/claim_review_screen.dart';
import 'package:mobile/features/journeys/data/journeys_service.dart';
import 'package:mobile/features/journeys/presentation/journey_detail_screen.dart';
import 'package:mobile/features/journeys/presentation/manual_journey_screen.dart';
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
  late final JourneysService _svc;
  late final TicketsService _tickets;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> journeys = [];
  bool matching = false;

  @override
  void initState() {
    super.initState();
    _svc = JourneysService(baseUrl: apiBaseUrl);
    _tickets = TicketsService(baseUrl: apiBaseUrl);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final list = await _svc.list(widget.deviceId);
      setState(() {
        journeys = list;
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

  void _openClaimReview(Map<String, dynamic> journey) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ClaimReviewScreen(journey: journey)),
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
    if (matching) return;
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (file == null) return;
    setState(() {
      matching = true;
      error = null;
    });
    try {
      final res = await _tickets.matchTicket(
        deviceId: widget.deviceId,
        file: file,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Billet uploadet: ${res['status'] ?? 'ok'}')),
      );
      await _load();
    } catch (e) {
      setState(() {
        error = '$e';
      });
    } finally {
      setState(() {
        matching = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: const EdgeInsets.all(12.0),
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
                    padding: const EdgeInsets.all(12.0),
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
          : ListView.builder(
              itemCount: journeys.length,
              itemBuilder: (context, index) {
                final j = journeys[index];
                final dep = (j['dep_station'] ?? j['start'] ?? '').toString();
                final arr = (j['arr_station'] ?? j['end'] ?? '').toString();
                final routeLabel = (j['route_label'] ?? '').toString();
                final delay = (j['delay_minutes'] ?? j['delay'] ?? '')
                    .toString();
                final status = (j['status'] ?? '').toString();
                final normalizedStatus = status.toLowerCase();
                final readyForReview = [
                  'ended',
                  'review',
                  'ready',
                ].contains(normalizedStatus);
                final statusColor = readyForReview
                    ? Colors.orange
                    : Colors.blue;
                return Card(
                  child: ListTile(
                    title: Text(
                      routeLabel.isNotEmpty
                          ? routeLabel
                          : (dep.isEmpty && arr.isEmpty
                                ? 'Ukendt rejse'
                                : '$dep -> $arr'),
                    ),
                    subtitle: Text(
                      delay.isEmpty
                          ? 'Status: $status'
                          : 'Forsinkelse: $delay min | Status: $status',
                    ),
                    trailing: SizedBox(
                      width: 140,
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: statusColor.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              status,
                              style: TextStyle(color: statusColor),
                            ),
                          ),
                          const SizedBox(height: 6),
                          TextButton(
                            onPressed: () => _openClaimReview(j),
                            child: Text(readyForReview ? 'Review' : 'Open'),
                          ),
                        ],
                      ),
                    ),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => JourneyDetailScreen(journey: j),
                        ),
                      );
                    },
                  ),
                );
              },
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
}
