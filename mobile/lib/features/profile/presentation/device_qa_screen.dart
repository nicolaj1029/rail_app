import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import 'package:mobile/config.dart';
import 'package:mobile/services/api_client.dart';
import 'package:mobile/services/device_service.dart';
import 'package:mobile/services/notifications_service.dart';

class DeviceQaScreen extends StatefulWidget {
  final String? initialDeviceId;

  const DeviceQaScreen({super.key, required this.initialDeviceId});

  @override
  State<DeviceQaScreen> createState() => _DeviceQaScreenState();
}

class _DeviceQaScreenState extends State<DeviceQaScreen> {
  late final DeviceService _deviceService;
  late final NotificationsService _notificationsService;

  String? _deviceId;
  bool _loading = true;
  bool _registering = false;
  bool _testingNotifications = false;
  bool _requestingLocation = false;
  String? _error;
  bool? _locationServiceEnabled;
  LocationPermission? _locationPermission;
  String _notificationState = 'Ikke testet endnu';

  @override
  void initState() {
    super.initState();
    _deviceService = DeviceService(ApiClient(baseUrl: apiBaseUrl));
    _notificationsService = NotificationsService();
    _deviceId = widget.initialDeviceId;
    _loadDiagnostics();
  }

  Future<void> _loadDiagnostics() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      final permission = await Geolocator.checkPermission();
      if (!mounted) {
        return;
      }
      setState(() {
        _locationServiceEnabled = enabled;
        _locationPermission = permission;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _registerDevice() async {
    setState(() {
      _registering = true;
      _error = null;
    });
    try {
      final id = await _deviceService.ensureRegistered();
      if (!mounted) {
        return;
      }
      setState(() {
        _deviceId = id;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _registering = false;
        });
      }
    }
  }

  Future<void> _requestLocationPermission() async {
    setState(() {
      _requestingLocation = true;
      _error = null;
    });
    try {
      final permission = await Geolocator.requestPermission();
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!mounted) {
        return;
      }
      setState(() {
        _locationPermission = permission;
        _locationServiceEnabled = enabled;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _requestingLocation = false;
        });
      }
    }
  }

  Future<void> _testNotification() async {
    setState(() {
      _testingNotifications = true;
      _error = null;
      _notificationState = 'Kører test...';
    });
    try {
      await _notificationsService.init();
      await _notificationsService.showNow(
        'Rail app test',
        'Lokal notifikation virker på denne enhed.',
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _notificationState = 'Test sendt';
      });
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _notificationState = 'Fejl';
        _error = '$e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _testingNotifications = false;
        });
      }
    }
  }

  String get _platformLabel {
    if (kIsWeb) {
      return 'web';
    }
    return defaultTargetPlatform.name;
  }

  String _permissionLabel(LocationPermission? permission) {
    switch (permission) {
      case LocationPermission.always:
        return 'Always';
      case LocationPermission.whileInUse:
        return 'While in use';
      case LocationPermission.denied:
        return 'Denied';
      case LocationPermission.deniedForever:
        return 'Denied forever';
      case LocationPermission.unableToDetermine:
        return 'Unable to determine';
      case null:
        return 'Ukendt';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Device QA')),
      body: RefreshIndicator(
        onRefresh: _loadDiagnostics,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Device diagnostics', style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 8),
            const Text(
              'Brug denne skærm på fysisk enhed før du tester upload, tracking og notifications.',
            ),
            const SizedBox(height: 16),
            if (_error != null)
              Card(
                color: Colors.red.shade50,
                child: ListTile(
                  leading: const Icon(Icons.error_outline),
                  title: const Text('Der opstod en fejl'),
                  subtitle: Text(_error!),
                ),
              ),
            _InfoCard(
              title: 'Miljø',
              items: [
                _InfoRow(label: 'Platform', value: _platformLabel),
                _InfoRow(label: 'API base', value: apiBaseUrl),
                _InfoRow(label: 'Device ID', value: _deviceId?.isNotEmpty == true ? _deviceId! : 'Ikke registreret'),
              ],
              action: ElevatedButton.icon(
                onPressed: _registering ? null : _registerDevice,
                icon: const Icon(Icons.phone_android_outlined),
                label: Text(_registering ? 'Registrerer...' : 'Registrer / opdatér device'),
              ),
            ),
            const SizedBox(height: 12),
            _InfoCard(
              title: 'Lokation',
              items: [
                _InfoRow(
                  label: 'Location service',
                  value: _loading
                      ? 'Læser...'
                      : ((_locationServiceEnabled ?? false) ? 'Aktiv' : 'Slået fra'),
                ),
                _InfoRow(
                  label: 'Permission',
                  value: _loading ? 'Læser...' : _permissionLabel(_locationPermission),
                ),
              ],
              action: ElevatedButton.icon(
                onPressed: _requestingLocation ? null : _requestLocationPermission,
                icon: const Icon(Icons.location_on_outlined),
                label: Text(_requestingLocation ? 'Anmoder...' : 'Anmod om lokation'),
              ),
            ),
            const SizedBox(height: 12),
            _InfoCard(
              title: 'Notifications',
              items: [
                _InfoRow(label: 'Status', value: _notificationState),
                _InfoRow(
                  label: 'Native test',
                  value: kIsWeb ? 'Brug fysisk Android/iOS for reel test' : 'Klar til lokal test',
                ),
              ],
              action: ElevatedButton.icon(
                onPressed: _testingNotifications ? null : _testNotification,
                icon: const Icon(Icons.notifications_active_outlined),
                label: Text(_testingNotifications ? 'Tester...' : 'Send testnotifikation'),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              color: Colors.blue.shade50,
              child: const ListTile(
                leading: Icon(Icons.checklist_outlined),
                title: Text('Næste device-pass'),
                subtitle: Text(
                  'Kør herefter upload fra kamera/galleri, live assist, chat upload og background/resume på fysisk enhed.',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final String title;
  final List<_InfoRow> items;
  final Widget action;

  const _InfoCard({
    required this.title,
    required this.items,
    required this.action,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            for (final item in items) ...[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 120,
                    child: Text(
                      item.label,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                  Expanded(child: Text(item.value)),
                ],
              ),
              const SizedBox(height: 8),
            ],
            const SizedBox(height: 8),
            action,
          ],
        ),
      ),
    );
  }
}

class _InfoRow {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});
}
