import 'dart:convert';

import 'package:http/http.dart' as http;

class ClaimsService {
  final String baseUrl;

  ClaimsService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> list() async {
    final uri = Uri.parse('$baseUrl/api/shadow/cases');
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final body = json.decode(res.body) as Map<String, dynamic>;
      final data = body['data'];
      final cases = (data is Map && data['cases'] is List)
          ? (data['cases'] as List)
          : (body['cases'] as List? ?? []);
      return cases.cast<Map<String, dynamic>>();
    }

    throw Exception('Claims fetch failed: ${res.statusCode}');
  }
}
