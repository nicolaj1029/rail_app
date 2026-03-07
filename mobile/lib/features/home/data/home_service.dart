import 'dart:convert';

import 'package:http/http.dart' as http;

class HomeService {
  final String baseUrl;

  HomeService({required this.baseUrl});

  Future<Map<String, dynamic>> load(String? deviceId) async {
    final query = <String, String>{};
    if (deviceId != null && deviceId.trim().isNotEmpty) {
      query['device_id'] = deviceId.trim();
    }

    final uri = Uri.parse(
      '$baseUrl/api/mobile/home',
    ).replace(queryParameters: query.isEmpty ? null : query);
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final body = json.decode(res.body) as Map<String, dynamic>;
      final data = (body['data'] as Map?)?.cast<String, dynamic>();
      return data ?? const <String, dynamic>{};
    }

    throw Exception('Home summary failed: ${res.statusCode}');
  }
}
