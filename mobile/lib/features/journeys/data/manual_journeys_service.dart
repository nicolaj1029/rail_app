import 'dart:convert';

import 'package:http/http.dart' as http;

class ManualJourneysService {
  final String baseUrl;
  ManualJourneysService({required this.baseUrl});

  Future<Map<String, dynamic>> submit(Map<String, dynamic> payload) async {
    final res = await http.post(
      Uri.parse('$baseUrl/api/manual_journeys'),
      headers: const {'Content-Type': 'application/json'},
      body: jsonEncode(payload),
    );
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('API ${res.statusCode}: ${res.body}');
    }
    return jsonDecode(res.body) as Map<String, dynamic>;
  }
}
