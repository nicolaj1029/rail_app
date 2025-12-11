import 'dart:async';

/// Stub receipt service to simulate OCR/LLM parsing.
/// In production, integrate image_picker + ML Kit and send raw text to backend.
class ReceiptService {
  Future<Map<String, dynamic>> scanDemo() async {
    await Future.delayed(const Duration(milliseconds: 300));
    return {
      'type': 'taxi',
      'amount': '250.00',
      'currency': 'DKK',
      'date': DateTime.now().toIso8601String(),
      'confidence': 0.7,
    };
  }
}
