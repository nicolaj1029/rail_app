import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';

import 'package:mobile/shared/network/persistent_http_client.dart';

class ChatService {
  final String baseUrl;
  final http.Client _client;

  ChatService({required this.baseUrl, http.Client? client})
    : _client = client ?? createPersistentHttpClient();

  Future<Map<String, dynamic>> bootstrap() async {
    final response = await _client.get(
      Uri.parse('$baseUrl/api/chat/bootstrap'),
    );
    return _decode(response);
  }

  Future<Map<String, dynamic>> sendMessage(String message) async {
    final response = await _client.post(
      Uri.parse('$baseUrl/api/chat/message'),
      headers: const {'Content-Type': 'application/json'},
      body: jsonEncode({'message': message}),
    );

    return _decode(response);
  }

  Future<Map<String, dynamic>> reset() async {
    final response = await _client.post(
      Uri.parse('$baseUrl/api/chat/reset'),
      headers: const {'Content-Type': 'application/json'},
      body: jsonEncode(const {}),
    );

    return _decode(response);
  }

  Future<Map<String, dynamic>> upload(XFile file) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/api/chat/upload'),
    );
    final bytes = await file.readAsBytes();
    final filename = file.name.isNotEmpty ? file.name : 'upload.bin';
    request.files.add(
      http.MultipartFile.fromBytes(
        'ticket_upload',
        bytes,
        filename: filename,
        contentType: _contentTypeFor(filename),
      ),
    );

    final streamed = await _client.send(request);
    final response = await http.Response.fromStream(streamed);

    return _decode(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('API ${response.statusCode}: ${response.body}');
    }

    return jsonDecode(response.body) as Map<String, dynamic>;
  }

  MediaType _contentTypeFor(String filename) {
    final lower = filename.toLowerCase();
    if (lower.endsWith('.png')) {
      return MediaType('image', 'png');
    }
    if (lower.endsWith('.pdf')) {
      return MediaType('application', 'pdf');
    }
    if (lower.endsWith('.txt')) {
      return MediaType('text', 'plain');
    }
    return MediaType('image', 'jpeg');
  }

  void dispose() {
    _client.close();
  }
}
