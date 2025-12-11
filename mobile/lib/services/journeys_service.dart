import 'dart:convert';

import 'package:http/http.dart' as http;

class JourneysService {
  final String baseUrl;
  JourneysService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> list(String deviceId) async {
    final uri = Uri.parse('$baseUrl/api/shadow/journeys').replace(
      queryParameters: {'device_id': deviceId},
    );
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final body = json.decode(res.body) as Map<String, dynamic>;
      final data = body['data'];
      final journeys = (data is Map && data['journeys'] is List)
          ? (data['journeys'] as List)
          : (body['journeys'] as List? ?? []);
      return journeys.cast<Map<String, dynamic>>();
    }
    throw Exception('Journeys fetch failed: ${res.statusCode}');
  }

  Future<Map<String, dynamic>> confirm(String id) async {
    final uri = Uri.parse('$baseUrl/api/shadow/journeys/$id/confirm');
    final res = await http.post(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      return json.decode(res.body) as Map<String, dynamic>;
    }
    throw Exception('Confirm failed: ${res.statusCode}');
  }
}
