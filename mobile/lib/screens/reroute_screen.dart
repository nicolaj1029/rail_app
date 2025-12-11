import 'package:flutter/material.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/reroute_service.dart';

class RerouteScreen extends StatefulWidget {
  final String destination;
  final String? deviceId;
  const RerouteScreen({super.key, required this.destination, this.deviceId});

  @override
  State<RerouteScreen> createState() => _RerouteScreenState();
}

class _RerouteScreenState extends State<RerouteScreen> {
  final RerouteService _service = RerouteService();
  late final ApiClient api;
  bool loading = true;
  List<Map<String, String>> options = [];

  @override
  void initState() {
    super.initState();
    api = ApiClient(baseUrl: apiBaseUrl);
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    final list = await _service.fetchOptions(widget.destination);
    setState(() {
      options = list;
      loading = false;
    });
  }

  Future<void> _select(Map<String, String> option) async {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Valgte: ${option['title']}')),
    );
    if (widget.deviceId != null && widget.deviceId!.isNotEmpty) {
      try {
        await api.post('/api/events/add', {
          'device_id': widget.deviceId,
          'type': 'reroute_selected',
          'payload': option,
        });
      } catch (_) {
        // ignore
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Alternativ rute til ${widget.destination}')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: options.length,
              itemBuilder: (context, index) {
                final o = options[index];
                return Card(
                  child: ListTile(
                    title: Text(o['title'] ?? ''),
                    subtitle: Text('${o['eta'] ?? ''}\n${o['desc'] ?? ''}'),
                    trailing: ElevatedButton(
                      onPressed: () => _select(o),
                      child: const Text('Vælg'),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
