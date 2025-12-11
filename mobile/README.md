# Rail App Mobile (Flutter)

This folder hosts the Flutter app for Android+iOS.

## Prerequisites (Windows)
- Install Flutter SDK (Chocolatey or manual)
- Android Studio or VS Code with Flutter plugin
- Android SDK platform-tools; enable USB debugging on device

## Quick setup
```powershell
choco install flutter -y
flutter doctor
flutter devices
cd C:\wamp64\www\rail_app
flutter create mobile
cd mobile
flutter pub add http geolocator geocoding workmanager flutter_local_notifications
flutter run
```

## Suggested structure (after `flutter create mobile`)
```
mobile/
  lib/
    services/
      api_client.dart
      device_service.dart
      shadow_tracker.dart
      geofence_manager.dart
    models/
      station.dart
      shadow_journey.dart
      event.dart
      reroute_option.dart
    screens/
      onboarding_screen.dart
      live_assist_screen.dart
      journeys_list_screen.dart
      case_close_screen.dart
    widgets/
      live_assist_cards.dart
      expense_form.dart
      reroute_list.dart
```

## Backend endpoints (CakePHP)
- POST `/api/shadow/devices/register` → returns `device_id`
- GET `/api/stations` → station geofences (to be implemented)
- POST `/api/shadow/pings` → batch GPS pings
- GET `/api/shadow/journeys?device_id=...` → detected journeys
- POST `/api/shadow/journeys/{id}/confirm` → create case, returns `case_id`

## Next steps
- Run `flutter create mobile` to generate the app shell
- Implement `services/api_client.dart` to call the above endpoints
- Implement `services/api_client.dart` to call the above endpoints (added)
- Build `live_assist_screen.dart` with cards for offers, expenses, status, and reroute
- Wire Google Directions API in a `RerouteService` (client-side)