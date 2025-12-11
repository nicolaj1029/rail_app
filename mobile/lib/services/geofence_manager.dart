import 'dart:async';
import 'dart:math';

class GeofenceEvent {
  final String stationId;
  final String type; // enter, exit, dwell
  final DateTime at;
  GeofenceEvent({
    required this.stationId,
    required this.type,
    required this.at,
  });
}

class GeofenceManager {
  final List<Map<String, dynamic>> stations;
  final Duration dwellThreshold;
  final void Function(GeofenceEvent) onEvent;

  String? _currentStation;
  Timer? _dwellTimer;

  GeofenceManager({
    required this.stations,
    required this.onEvent,
    this.dwellThreshold = const Duration(minutes: 3),
  });

  void updatePosition(double lat, double lon) {
    final s = _nearestWithinRadius(lat, lon);
    final now = DateTime.now();
    if (s != null) {
      final id = s['id'] as String;
      if (_currentStation != id) {
        _currentStation = id;
        onEvent(GeofenceEvent(stationId: id, type: 'enter', at: now));
        _dwellTimer?.cancel();
        _dwellTimer = Timer(dwellThreshold, () {
          if (_currentStation == id) {
            onEvent(
              GeofenceEvent(stationId: id, type: 'dwell', at: DateTime.now()),
            );
          }
        });
      }
    } else {
      if (_currentStation != null) {
        final id = _currentStation!;
        _currentStation = null;
        _dwellTimer?.cancel();
        onEvent(GeofenceEvent(stationId: id, type: 'exit', at: now));
      }
    }
  }

  Map<String, dynamic>? _nearestWithinRadius(double lat, double lon) {
    Map<String, dynamic>? best;
    double bestDist = double.infinity;
    for (final s in stations) {
      final d = _haversine(lat, lon, s['lat'] as num, s['lon'] as num);
      final within = (d * 1000) <= (s['radius_m'] as num).toDouble();
      if (within && d < bestDist) {
        bestDist = d;
        best = s;
      }
    }
    return best;
  }

  // distance in kilometers
  double _haversine(double lat1, double lon1, num lat2, num lon2) {
    const R = 6371.0; // km
    final dLat = _deg2rad(lat2.toDouble() - lat1);
    final dLon = _deg2rad(lon2.toDouble() - lon1);
    final a =
        sin(dLat / 2) * sin(dLat / 2) +
        cos(_deg2rad(lat1)) *
            cos(_deg2rad(lat2.toDouble())) *
            sin(dLon / 2) *
            sin(dLon / 2);
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return R * c;
  }

  double _deg2rad(double deg) => deg * (pi / 180.0);
}
