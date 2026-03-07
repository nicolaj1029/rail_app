import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

class DeviceService {
  final ApiClient api;
  DeviceService(this.api);

  static const _keyDeviceId = 'device_id';

  Future<String> ensureRegistered() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_keyDeviceId);
    if (existing != null && existing.isNotEmpty) return existing;

    final json = await api.post('/api/shadow/devices/register', {
      'platform': _platformLabel(),
      'push_token': '',
    });
    final data = json['data'] as Map<String, dynamic>;
    final deviceId = (data['device_id'] as String?) ?? '';
    await prefs.setString(_keyDeviceId, deviceId);
    return deviceId;
  }

  String _platformLabel() {
    if (kIsWeb) return 'web';
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android';
      case TargetPlatform.iOS:
        return 'ios';
      case TargetPlatform.macOS:
        return 'macos';
      case TargetPlatform.windows:
        return 'windows';
      case TargetPlatform.linux:
        return 'linux';
      case TargetPlatform.fuchsia:
        return 'fuchsia';
    }
  }
}
