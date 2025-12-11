import 'package:flutter/material.dart';

import 'screens/live_assist_screen.dart';

void main() {
  runApp(const RailApp());
}

class RailApp extends StatelessWidget {
  const RailApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Rail Live Assist',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.indigo),
        useMaterial3: true,
      ),
      home: const LiveAssistScreen(),
    );
  }
}
