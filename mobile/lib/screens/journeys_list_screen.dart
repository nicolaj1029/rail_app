import 'package:flutter/material.dart';

import '../config.dart';
import '../services/journeys_service.dart';
import 'case_close_screen.dart';

class JourneysListScreen extends StatefulWidget {
  final String deviceId;
  const JourneysListScreen({super.key, required this.deviceId});

  @override
  State<JourneysListScreen> createState() => _JourneysListScreenState();
}

class _JourneysListScreenState extends State<JourneysListScreen> {
  late final JourneysService _svc;
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> journeys = [];

  @override
  void initState() {
    super.initState();
    _svc = JourneysService(baseUrl: apiBaseUrl);
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

  void _openCaseClose(Map<String, dynamic> journey) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CaseCloseScreen(journey: journey),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
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
      body: Padding(
        padding: const EdgeInsets.all(12.0),
        child: loading
            ? const Center(child: CircularProgressIndicator())
            : error != null
                ? Center(child: Text('Error: $error'))
                : ListView.builder(
                    itemCount: journeys.length,
                    itemBuilder: (context, index) {
                      final j = journeys[index];
                      final id = (j['id'] ?? '').toString();
                      final dep = (j['dep_station'] ?? j['start'] ?? '').toString();
                      final arr = (j['arr_station'] ?? j['end'] ?? '').toString();
                      final delay = (j['delay_minutes'] ?? j['delay'] ?? '').toString();
                      final status = (j['status'] ?? '').toString();
                      final ended = status.toLowerCase() == 'ended';
                      return Card(
                        child: ListTile(
                          title: Text('Journey $id'),
                          subtitle: Text('$dep -> $arr  | delay: $delay min | $status'),
                          trailing: TextButton(
                            onPressed: () => _openCaseClose(j),
                            child: Text(ended ? 'Case Close' : 'Open'),
                          ),
                        ),
                      );
                    },
                  ),
      ),
    );
  }
}
