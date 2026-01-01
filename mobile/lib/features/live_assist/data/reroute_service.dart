import 'dart:async';

/// Stub reroute service. In production, call Directions/Transit API.
class RerouteService {
  Future<List<Map<String, String>>> fetchOptions(String destination) async {
    await Future.delayed(const Duration(milliseconds: 200));
    return [
      {
        'title': 'Tog + bus',
        'eta': 'Ankomst +32 min',
        'desc': 'Via mellemliggende station, 1 skift',
      },
      {
        'title': 'Kun tog',
        'eta': 'Ankomst +52 min',
        'desc': 'Direkte tog, ingen skift',
      },
      {
        'title': 'Taxi',
        'eta': 'ETA 25 min',
        'desc': 'Ca. 850 kr (estimat)',
      },
    ];
  }
}
