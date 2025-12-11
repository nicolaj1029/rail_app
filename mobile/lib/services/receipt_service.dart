import 'dart:async';
import 'dart:io';

import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';
import 'package:image_picker/image_picker.dart';

/// Receipt scanning using ImagePicker + ML Kit text recognition.
/// Falls back to stub if parsing fails.
class ReceiptService {
  final ImagePicker _picker = ImagePicker();
  final TextRecognizer _recognizer = TextRecognizer(script: TextRecognitionScript.latin);

  Future<Map<String, dynamic>> scanAndParse() async {
    // Let user pick from camera first, else gallery.
    XFile? file = await _picker.pickImage(source: ImageSource.camera);
    file ??= await _picker.pickImage(source: ImageSource.gallery);
    if (file == null) return _stub();

    final inputImage = InputImage.fromFilePath(file.path);
    final result = await _recognizer.processImage(inputImage);
    final text = result.text;
    if (text.isEmpty) return _stub();

    // Simple heuristics to extract amount/currency/date
    final amountMatch = RegExp(r'(\d+[.,]\d{2})').firstMatch(text);
    final currencyMatch = RegExp(r'\b(DKK|EUR|USD|SEK|NOK|GBP)\b', caseSensitive: false).firstMatch(text);
    final dateMatch = RegExp(r'(\d{4}-\d{2}-\d{2})').firstMatch(text);

    return {
      'type': _inferType(text),
      'amount': amountMatch?.group(1)?.replaceAll(',', '.') ?? '',
      'currency': currencyMatch?.group(1)?.toUpperCase() ?? '',
      'date': dateMatch?.group(1) ?? DateTime.now().toIso8601String(),
      'raw_text': text,
    };
  }

  String _inferType(String text) {
    final t = text.toLowerCase();
    if (t.contains('taxi') || t.contains('cab')) return 'taxi';
    if (t.contains('hotel') || t.contains('overnat')) return 'hotel';
    if (t.contains('restaurant') || t.contains('cafe') || t.contains('meal')) return 'meals';
    if (t.contains('bus')) return 'bus';
    return 'other';
  }

  Map<String, dynamic> _stub() {
    return {
      'type': 'other',
      'amount': '',
      'currency': '',
      'date': DateTime.now().toIso8601String(),
      'raw_text': '',
    };
  }
}
