class ChatContext {
  final String title;
  final String subtitle;
  final Map<String, dynamic> payload;
  final List<String> suggestions;
  final String stageLabel;
  final String stageDescription;
  final String stageTone;

  const ChatContext({
    required this.title,
    required this.subtitle,
    required this.payload,
    required this.suggestions,
    required this.stageLabel,
    required this.stageDescription,
    required this.stageTone,
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
      if (_statusLabel(status).isNotEmpty) _statusLabel(status),
    ];

    final suggestions = _suggestionsForStatus(
      status: status,
      delayMinutes: delayMinutes,
      ticketMode: ticketMode,
    );
    final stageMeta = _stageMeta(status);

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
      stageLabel: stageMeta.label,
      stageDescription: stageMeta.description,
      stageTone: stageMeta.tone,
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

  static String _statusLabel(String status) {
    switch (status) {
      case 'ended':
      case 'review':
      case 'ready':
        return 'klar til review';
      case 'active':
      case 'in_progress':
      case 'detected':
      case 'ongoing':
        return 'rejse i gang';
      case 'submitted':
      case 'sent':
      case 'waiting':
      case 'open':
        return 'indsendt / i gang';
      case 'paid':
      case 'closed':
      case 'resolved':
        return 'afsluttet';
      default:
        return status.trim();
    }
  }

  static List<String> _suggestionsForStatus({
    required String status,
    required int? delayMinutes,
    required String ticketMode,
  }) {
    final normalized = status.trim().toLowerCase();
    final suggestions = <String>[];

    if (['ended', 'review', 'ready'].contains(normalized)) {
      suggestions.addAll([
        'Hvad mangler der endnu før vi kan sende sagen?',
        'Hvilket næste trin anbefaler du for denne rejse?',
      ]);
      if (delayMinutes != null && delayMinutes > 0) {
        suggestions.add('Er $delayMinutes min nok til kompensation?');
      }
      if (ticketMode == 'seasonpass') {
        suggestions.add('Hvordan håndterer vi pendler/season pass her?');
      }
    } else if ([
      'active',
      'in_progress',
      'detected',
      'ongoing',
    ].contains(normalized)) {
      suggestions.addAll([
        'Hvad bør jeg registrere nu under rejsen?',
        'Skal jeg bruge live assist eller vente til review?',
        'Er der noget vigtigt jeg mangler at dokumentere?',
      ]);
      if (delayMinutes != null && delayMinutes > 0) {
        suggestions.add('Hvad betyder $delayMinutes min forsinkelse lige nu?');
      }
    } else if (['submitted', 'sent', 'waiting', 'open'].contains(normalized)) {
      suggestions.addAll([
        'Er der mere vi bør tilføje til den indsendte sag?',
        'Hvad er næste forventede skridt nu?',
        'Mangler der dokumentation for at styrke sagen?',
      ]);
    } else if (['paid', 'closed', 'resolved'].contains(normalized)) {
      suggestions.addAll([
        'Kan du opsummere udfaldet af denne sag?',
        'Er der noget vi bør gemme til lignende rejser?',
      ]);
    } else {
      suggestions.addAll([
        'Hvad mangler der endnu i denne sag?',
        'Hvilket næste trin anbefaler du?',
      ]);
      if (delayMinutes != null && delayMinutes > 0) {
        suggestions.add('Er $delayMinutes min nok til kompensation?');
      }
      if (ticketMode == 'seasonpass') {
        suggestions.add('Hvordan håndterer vi pendler/season pass her?');
      }
    }

    return suggestions.toSet().toList();
  }

  static _StageMeta _stageMeta(String status) {
    final normalized = status.trim().toLowerCase();
    if (['ended', 'review', 'ready'].contains(normalized)) {
      return const _StageMeta(
        label: 'Review',
        description:
            'Chatten bør fokusere på manglende oplysninger, eligibility og næste trin før sagen sendes.',
        tone: 'review',
      );
    }
    if (['active', 'in_progress', 'detected', 'ongoing'].contains(normalized)) {
      return const _StageMeta(
        label: 'Live',
        description:
            'Chatten bør fokusere på hvad der skal registreres nu under rejsen og hvad der kan vente til review.',
        tone: 'live',
      );
    }
    if (['submitted', 'sent', 'waiting', 'open'].contains(normalized)) {
      return const _StageMeta(
        label: 'Submitted',
        description:
            'Chatten bør fokusere på opfølgning, manglende dokumentation og hvad der realistisk sker næste gang.',
        tone: 'submitted',
      );
    }
    if (['paid', 'closed', 'resolved'].contains(normalized)) {
      return const _StageMeta(
        label: 'Completed',
        description:
            'Chatten bør fokusere på opsummering, læring og om noget skal gemmes til senere sager.',
        tone: 'completed',
      );
    }

    return const _StageMeta(
      label: 'General',
      description:
          'Chatten bruges som hjælpelag oven på den aktuelle sag og bør fokusere på næste relevante handling.',
      tone: 'general',
    );
  }
}

class _StageMeta {
  final String label;
  final String description;
  final String tone;

  const _StageMeta({
    required this.label,
    required this.description,
    required this.tone,
  });
}
