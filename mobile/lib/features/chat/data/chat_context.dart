class ChatContext {
  final String title;
  final String subtitle;
  final Map<String, dynamic> payload;
  final List<String> suggestions;

  const ChatContext({
    required this.title,
    required this.subtitle,
    required this.payload,
    required this.suggestions,
  });

  factory ChatContext.fromJourney(
    Map<String, dynamic> journey, {
    required String source,
  }) {
    String readString(List<String> keys) {
      for (final key in keys) {
        final value = journey[key];
        if (value != null && value.toString().trim().isNotEmpty) {
          return value.toString().trim();
        }
      }
      return '';
    }

    int? readInt(List<String> keys) {
      for (final key in keys) {
        final value = journey[key];
        if (value == null) {
          continue;
        }
        if (value is int) {
          return value;
        }
        final parsed = int.tryParse(value.toString());
        if (parsed != null) {
          return parsed;
        }
      }
      return null;
    }

    final routeLabel = readString(['route_label']);
    final depStation = readString(['dep_station', 'start']);
    final arrStation = readString(['arr_station', 'end']);
    final operator = readString(['operator']);
    final operatorCountry = readString(['operator_country']);
    final status = readString(['status']).toLowerCase();
    final delayMinutes = readInt(['delay_minutes', 'delay']);
    final ticketMode = _ticketModeForJourney(journey);

    final displayRoute = routeLabel.isNotEmpty
        ? routeLabel
        : (depStation.isEmpty && arrStation.isEmpty
              ? 'Ukendt rejse'
              : '$depStation -> $arrStation');

    final subtitleParts = <String>[
      if (operator.isNotEmpty) operator,
      if (delayMinutes != null && delayMinutes > 0) '$delayMinutes min',
      if (status.isNotEmpty) status,
    ];

    final suggestions = <String>[
      'Hvad mangler der endnu i denne sag?',
      if (delayMinutes != null && delayMinutes > 0)
        'Er $delayMinutes min nok til kompensation?',
      if (ticketMode == 'seasonpass')
        'Hvordan håndterer vi pendler/season pass her?',
      'Hvilket næste trin anbefaler du?',
    ];

    return ChatContext(
      title: displayRoute,
      subtitle: subtitleParts.isEmpty
          ? 'Kontekst fra mobil rejse'
          : subtitleParts.join(' • '),
      payload: <String, dynamic>{
        'source': source,
        'journey_id': (journey['id'] ?? '').toString(),
        'device_id': readString(['device_id']),
        'route_label': displayRoute,
        'dep_station': depStation,
        'arr_station': arrStation,
        'operator': operator,
        'operator_country': operatorCountry,
        'ticket_mode': ticketMode,
        'delay_minutes': delayMinutes,
        'status': status,
        'incident_main': readString(['incident_main']),
      },
      suggestions: suggestions,
    );
  }

  static String _ticketModeForJourney(Map<String, dynamic> journey) {
    final explicit = (journey['ticket_upload_mode'] ?? '').toString().trim();
    if (explicit.isNotEmpty) {
      return explicit;
    }

    final ticketType = (journey['ticket_type'] ?? '').toString().toLowerCase();
    if (ticketType.contains('season') ||
        ticketType.contains('pendler') ||
        ticketType.contains('abonnement')) {
      return 'seasonpass';
    }

    return 'ticket';
  }
}
