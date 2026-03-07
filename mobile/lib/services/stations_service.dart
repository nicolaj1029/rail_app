import 'dart:convert';

import 'package:http/http.dart' as http;

class StationsService {
  final String baseUrl;
  StationsService({required this.baseUrl});

  Future<List<Map<String, dynamic>>> fetchStations({String? bbox}) async {
    final uri = Uri.parse(
      '$baseUrl/api/stations',
    ).replace(queryParameters: bbox != null ? {'bbox': bbox} : null);
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final jsonBody = json.decode(res.body) as Map<String, dynamic>;
      final data = jsonBody['data'] as Map<String, dynamic>?;
      final stations = (data?['stations'] as List?) ?? [];
      return stations.cast<Map<String, dynamic>>();
    }
    throw Exception('Failed to fetch stations: ${res.statusCode}');
  }

  Future<List<Map<String, dynamic>>> searchStations(
    String query, {
    String? country,
    int limit = 8,
  }) async {
    final trimmed = query.trim();
    if (trimmed.length < 2) {
      return const [];
    }

    final params = <String, String>{'q': trimmed, 'limit': '$limit'};
    if (country != null && country.trim().isNotEmpty) {
      params['country'] = country.trim();
    }

    final uri = Uri.parse(
      '$baseUrl/api/stations/search',
    ).replace(queryParameters: params);
    final res = await http.get(uri);
    if (res.statusCode >= 200 && res.statusCode < 300) {
      final jsonBody = json.decode(res.body) as Map<String, dynamic>;
      final data = jsonBody['data'] as Map<String, dynamic>?;
      final stations = (data?['stations'] as List?) ?? [];
      return stations.cast<Map<String, dynamic>>();
    }

    throw Exception('Failed to search stations: ${res.statusCode}');
  }
}
