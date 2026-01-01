import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

/// Simple client for uploading a ticket image and getting a matched journey.
class TicketsService {
  final String baseUrl;

  TicketsService({required this.baseUrl});

  /// Uploads a ticket image (photo or file) and asks backend to match journey.
  /// Throws if HTTP status is not 2xx.
  Future<Map<String, dynamic>> matchTicket({
    required String deviceId,
    String? journeyId,
    XFile? file,
    String? filePath,
  }) async {
    if (file == null && (filePath == null || filePath.isEmpty)) {
      throw ArgumentError('file or filePath must be provided');
    }

    final uri = Uri.parse('$baseUrl/api/tickets/match');
    final request = http.MultipartRequest('POST', uri)
      ..fields['device_id'] = deviceId;

    if (journeyId != null && journeyId.isNotEmpty) {
      request.fields['journey_id'] = journeyId;
    }

    if (file != null) {
      final bytes = await file.readAsBytes();
      final name = file.name.isNotEmpty ? file.name : 'ticket.jpg';
      request.files.add(http.MultipartFile.fromBytes(
        'image',
        bytes,
        filename: name,
      ));
    } else if (filePath != null && filePath.isNotEmpty) {
      request.files.add(await http.MultipartFile.fromPath('image', filePath));
    }

    final response = await request.send();
    final body = await response.stream.bytesToString();

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('Upload failed ${response.statusCode}: $body');
    }

    return jsonDecode(body) as Map<String, dynamic>;
  }
}
