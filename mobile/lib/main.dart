import 'package:flutter/material.dart';

import 'package:mobile/app/app_shell.dart';

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
      home: const AppShell(),
    );
  }
}
