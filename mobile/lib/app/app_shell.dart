import 'package:flutter/material.dart';

import 'package:mobile/config.dart';
import 'package:mobile/features/chat/presentation/chat_screen.dart';
import 'package:mobile/features/claims/presentation/claim_review_screen.dart';
import 'package:mobile/features/claims/presentation/claims_screen.dart';
import 'package:mobile/features/home/data/home_service.dart';
import 'package:mobile/features/home/presentation/home_screen.dart';
import 'package:mobile/features/journeys/data/journeys_service.dart';
import 'package:mobile/features/journeys/presentation/journeys_list_screen.dart';
import 'package:mobile/features/live_assist/presentation/live_assist_screen.dart';
import 'package:mobile/features/profile/data/commuter_profile_store.dart';
import 'package:mobile/features/profile/presentation/profile_screen.dart';
import 'package:mobile/services/api_client.dart';
import 'package:mobile/services/device_service.dart';

class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int selectedIndex = 0;
  bool loading = true;
  String? error;
  String? deviceId;
  List<Map<String, dynamic>> journeys = [];
  Map<String, dynamic> homeSummary = const {};
  CommuterProfile commuterProfile = CommuterProfile.empty();

  late final ApiClient api;
  late final DeviceService deviceService;
  late final JourneysService journeysService;
  late final HomeService homeService;
  late final CommuterProfileStore commuterProfileStore;

  bool get commuterMode =>
      commuterProfile.enabled && commuterProfile.isConfigured;

  @override
  void initState() {
    super.initState();
    api = ApiClient(baseUrl: apiBaseUrl);
    deviceService = DeviceService(api);
    journeysService = JourneysService(baseUrl: apiBaseUrl);
    homeService = HomeService(baseUrl: apiBaseUrl);
    commuterProfileStore = CommuterProfileStore();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final loadedProfile = await commuterProfileStore.load();
      final id = await deviceService.ensureRegistered();
      final list = await journeysService.list(id);
      final summary = await homeService.load(id);
      setState(() {
        commuterProfile = loadedProfile;
        deviceId = id;
        journeys = list;
        homeSummary = summary;
      });
    } catch (e) {
      setState(() {
        error = '$e';
      });
      try {
        final loadedProfile = await commuterProfileStore.load();
        setState(() {
          commuterProfile = loadedProfile;
        });
      } catch (_) {}
    } finally {
      setState(() {
        loading = false;
      });
    }
  }

  Future<void> _refreshJourneys() async {
    if (deviceId == null || deviceId!.isEmpty) {
      await _bootstrap();
      return;
    }
    setState(() {
      error = null;
    });
    try {
      final list = await journeysService.list(deviceId!);
      final summary = await homeService.load(deviceId);
      setState(() {
        journeys = list;
        homeSummary = summary;
      });
    } catch (e) {
      setState(() {
        error = '$e';
      });
    }
  }

  Future<void> _saveCommuterProfile(CommuterProfile profile) async {
    await commuterProfileStore.save(profile);
    setState(() {
      commuterProfile = profile;
    });
  }

  Map<String, dynamic>? get _primaryReviewJourney {
    for (final journey in journeys) {
      final status = (journey['status'] ?? '').toString().toLowerCase();
      if (['ended', 'ready', 'review'].contains(status)) {
        return journey;
      }
    }
    return journeys.isNotEmpty ? journeys.first : null;
  }

  void _openLiveAssist() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const LiveAssistScreen()));
  }

  void _openPrimaryReview() {
    final journey = _primaryReviewJourney;
    if (journey == null) {
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ClaimReviewScreen(journey: journey)),
    );
  }

  Widget _buildCurrentScreen() {
    switch (selectedIndex) {
      case 0:
        return HomeScreen(
          loading: loading,
          error: error,
          deviceId: deviceId,
          journeys: journeys,
          homeSummary: homeSummary,
          commuterProfile: commuterProfile,
          onRefresh: _bootstrap,
          onNavigate: (index) => setState(() => selectedIndex = index),
          onOpenLiveAssist: _openLiveAssist,
          onOpenPrimaryReview: _primaryReviewJourney == null
              ? null
              : _openPrimaryReview,
        );
      case 1:
        if (deviceId == null || deviceId!.isEmpty) {
          return const _MissingDeviceScreen();
        }
        return JourneysListScreen(deviceId: deviceId!, embedded: true);
      case 2:
        return ClaimsScreen(
          journeys: journeys,
          commuterMode: commuterMode,
          onRefresh: _refreshJourneys,
          onOpenJourney: (journey) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ClaimReviewScreen(journey: journey),
              ),
            );
          },
        );
      case 3:
        return ChatScreen(
          commuterMode: commuterMode,
          deviceId: deviceId,
          commuterProfile: commuterProfile,
        );
      case 4:
        return ProfileScreen(
          commuterProfile: commuterProfile,
          onSaveProfile: _saveCommuterProfile,
        );
      default:
        return const SizedBox.shrink();
    }
  }

  @override
  Widget build(BuildContext context) {
    final titles = [
      commuterMode ? 'Pendler-app' : 'Rail app',
      'Trips',
      'Claims',
      'Chat',
      'Profile',
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[selectedIndex]),
        actions: [
          IconButton(onPressed: _bootstrap, icon: const Icon(Icons.refresh)),
          IconButton(
            onPressed: _openLiveAssist,
            icon: const Icon(Icons.play_circle_outline),
            tooltip: 'Open live assist',
          ),
          if (_primaryReviewJourney != null)
            IconButton(
              onPressed: _openPrimaryReview,
              icon: const Icon(Icons.assignment_outlined),
              tooltip: 'Open review',
            ),
        ],
      ),
      body: _buildCurrentScreen(),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedIndex,
        onDestinationSelected: (index) => setState(() => selectedIndex = index),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.timeline_outlined),
            selectedIcon: Icon(Icons.timeline),
            label: 'Trips',
          ),
          NavigationDestination(
            icon: Icon(Icons.description_outlined),
            selectedIcon: Icon(Icons.description),
            label: 'Claims',
          ),
          NavigationDestination(
            icon: Icon(Icons.chat_bubble_outline),
            selectedIcon: Icon(Icons.chat_bubble),
            label: 'Chat',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

class _MissingDeviceScreen extends StatelessWidget {
  const _MissingDeviceScreen();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(24),
        child: Text(
          'Device er ikke registreret endnu. Brug refresh eller åbn appen igen.',
        ),
      ),
    );
  }
}
