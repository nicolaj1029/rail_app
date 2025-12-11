import 'package:flutter/material.dart';

import '../services/reroute_service.dart';

class RerouteScreen extends StatefulWidget {
  final String destination;
  const RerouteScreen({super.key, required this.destination});

  @override
  State<RerouteScreen> createState() => _RerouteScreenState();
}

class _RerouteScreenState extends State<RerouteScreen> {
  final RerouteService _service = RerouteService();
  bool loading = true;
  List<Map<String, String>> options = [];

  @override
  void initState() {
    super.initState();
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
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Valgte: ${o['title']} (stub)')),
                        );
                      },
                      child: const Text('Vælg'),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
