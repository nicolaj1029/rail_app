import 'dart:convert';

import 'package:http/http.dart' as http;

class EventsService {
  final String baseUrl;
  EventsService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> list({String? deviceId, int? limit}) async {
    final params = <String, String>{};
    if (deviceId != null && deviceId.isNotEmpty) params['device_id'] = deviceId;
    if (limit != null) params['limit'] = '$limit';
    final uri = Uri.parse('$baseUrl/api/events').replace(queryParameters: params.isEmpty ? null : params);
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final body = json.decode(res.body) as Map<String, dynamic>;
      final data = body['data'] as Map<String, dynamic>? ?? {};
      final list = (data['events'] as List?) ?? (body['events'] as List? ?? []);
      return list.cast<Map<String, dynamic>>();
    }
    // Fallback to empty timeline if endpoint missing
    return [];
  }
}
