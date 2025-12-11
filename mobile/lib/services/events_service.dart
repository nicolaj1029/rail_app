import 'dart:convert';

import 'package:http/http.dart' as http;

class EventsService {
  final String baseUrl;
  EventsService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> list(String journeyId) async {
    // Stub: backend not implemented; returns empty list
    final uri = Uri.parse('$baseUrl/api/events?journey_id=$journeyId');
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final body = json.decode(res.body) as Map<String, dynamic>;
      final data = body['data'] as Map<String, dynamic>? ?? {};
      final list = (data['events'] as List?) ?? [];
      return list.cast<Map<String, dynamic>>();
    }
    // Fallback to empty timeline if endpoint missing
    return [];
  }
}
