import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Simple offline queue for pings and events using SharedPreferences.
class OfflineQueue {
  static const _keyPings = 'offline_pings';
  static const _keyEvents = 'offline_events';

  Future<void> addPingBatch(Map<String, dynamic> batch) async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_keyPings) ?? [];
    list.add(jsonEncode(batch));
    await prefs.setStringList(_keyPings, list);
  }

  Future<List<Map<String, dynamic>>> takePingBatches() async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_keyPings) ?? [];
    await prefs.remove(_keyPings);
    return list.map((e) => jsonDecode(e) as Map<String, dynamic>).toList();
  }

  Future<void> addEvent(Map<String, dynamic> event) async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_keyEvents) ?? [];
    list.add(jsonEncode(event));
    await prefs.setStringList(_keyEvents, list);
  }

  Future<List<Map<String, dynamic>>> takeEvents() async {
    final prefs = await SharedPreferences.getInstance();
    final list = prefs.getStringList(_keyEvents) ?? [];
    await prefs.remove(_keyEvents);
    return list.map((e) => jsonDecode(e) as Map<String, dynamic>).toList();
  }
}
