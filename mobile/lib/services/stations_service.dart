import 'dart:convert';

import 'package:http/http.dart' as http;

class StationsService {
  final String baseUrl;
  StationsService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> fetchStations({String? bbox}) async {
    final uri = Uri.parse('$baseUrl/api/stations').replace(
      queryParameters: bbox != null ? {'bbox': bbox} : null,
    );
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final jsonBody = json.decode(res.body) as Map<String, dynamic>;
      final data = jsonBody['data'] as Map<String, dynamic>?;
      final stations = (data?['stations'] as List?) ?? [];
      return stations.cast<Map<String, dynamic>>();
    }
    throw Exception('Failed to fetch stations: ${res.statusCode}');
  }
}
