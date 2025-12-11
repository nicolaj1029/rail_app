import 'package:flutter/material.dart';

class RerouteScreen extends StatelessWidget {
  final String destination;
  const RerouteScreen({super.key, required this.destination});

  @override
  Widget build(BuildContext context) {
    final options = [
      {
        'title': 'Tog + bus',
        'eta': 'Ankomst 10:45 (+32 min)',
        'mode': 'mixed',
      },
      {
        'title': 'Kun tog',
        'eta': 'Ankomst 11:05 (+52 min)',
        'mode': 'rail',
      },
      {
        'title': 'Taxi',
        'eta': 'ETA 10:20 · ca. 850 kr',
        'mode': 'taxi',
      },
    ];
    return Scaffold(
      appBar: AppBar(title: Text('Alternativ rute til $destination')),
      body: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: options.length,
        itemBuilder: (context, index) {
          final o = options[index];
          return Card(
            child: ListTile(
              title: Text(o['title']!),
              subtitle: Text(o['eta']!),
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
