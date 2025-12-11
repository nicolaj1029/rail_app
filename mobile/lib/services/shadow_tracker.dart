import 'dart:async';

import 'package:geolocator/geolocator.dart';

import 'api_client.dart';
import 'geofence_manager.dart';
import 'offline_queue.dart';
import 'stations_service.dart';

class ShadowTracker {
  final ApiClient api;
  final String deviceId;
  final OfflineQueue _queue;
  final List<Map<String, dynamic>> _buffer = [];
  StreamSubscription<Position>? _sub;
  GeofenceManager? _geofence;

  ShadowTracker({required this.api, required this.deviceId, OfflineQueue? queue})
      : _queue = queue ?? OfflineQueue();

  Future<void> start() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return;
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return;
      }
    }

    // Bootstrap stations for geofencing
    try {
      final stationsSvc = StationsService(baseUrl: api.baseUrl);
      final stations = await stationsSvc.fetchStations();
      _geofence = GeofenceManager(
        stations: stations,
        onEvent: (e) {
          _postEvent(e.type, {
            'station_id': e.stationId,
            'at': e.at.toIso8601String(),
          });
        },
      );
    } catch (_) {}

    _sub =
        Geolocator.getPositionStream(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            distanceFilter: 25,
          ),
        ).listen((pos) {
          final ping = {
            't': DateTime.now().toUtc().toIso8601String(),
            'lat': pos.latitude,
            'lon': pos.longitude,
            'speed_kmh': (pos.speed * 3.6),
          };
          _buffer.add(ping);
          _geofence?.updatePosition(pos.latitude, pos.longitude);
          if (_buffer.length >= 20) {
            _flush();
          }
        });
    await _flushOfflinePings();
  }

  Future<void> stop() async {
    await _sub?.cancel();
    await _flush();
  }

  Future<void> _flush() async {
    if (_buffer.isEmpty) return;
    final batch = List<Map<String, dynamic>>.from(_buffer);
    _buffer.clear();
    final payload = {
      'device_id': deviceId,
      'pings': batch,
    };
    try {
      await api.post('/api/shadow/pings', payload);
    } catch (_) {
      await _queue.addPingBatch(payload);
    }
  }

  Future<void> _postEvent(String type, Map<String, dynamic> payload) async {
    try {
      await api.post('/api/events', {
        'device_id': deviceId,
        'type': type,
        'payload': payload,
      });
    } catch (_) {
      await _queue.addEvent({
        'device_id': deviceId,
        'type': type,
        'payload': payload,
      });
    }
  }

  Future<void> _flushOfflinePings() async {
    final batches = await _queue.takePingBatches();
    for (final payload in batches) {
      try {
        await api.post('/api/shadow/pings', payload);
      } catch (_) {
        await _queue.addPingBatch(payload);
      }
    }
  }
}