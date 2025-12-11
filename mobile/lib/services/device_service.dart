import 'dart:io';

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
      'platform': Platform.isAndroid
          ? 'android'
          : (Platform.isIOS ? 'ios' : 'unknown'),
      'push_token': '',
    });
    final data = json['data'] as Map<String, dynamic>;
    final deviceId = (data['device_id'] as String?) ?? '';
    await prefs.setString(_keyDeviceId, deviceId);
    return deviceId;
  }
}
