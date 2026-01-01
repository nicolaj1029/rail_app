import 'package:mobile/config.dart';
import 'package:mobile/services/api_client.dart';

class EvaluationService {
  final String baseUrl;
  final ApiClient _client;

  EvaluationService({String? baseUrl})
    : baseUrl = baseUrl ?? apiBaseUrl,
      _client = ApiClient(baseUrl: baseUrl ?? apiBaseUrl);

  /// Maps the mobile Case Close payload into the unified pipeline input
  /// and posts it to `/api/pipeline/run`.
  Future<Map<String, dynamic>> evaluateCaseClose(
    Map<String, dynamic> mobilePayload,
  ) async {
    final incident = (mobilePayload['incident'] ?? {}) as Map<String, dynamic>;
    final art18 = (mobilePayload['art18'] ?? {}) as Map<String, dynamic>;
    final art20 = (mobilePayload['art20'] ?? {}) as Map<String, dynamic>;
    final compensation =
        (mobilePayload['compensation'] ?? {}) as Map<String, dynamic>;
    final tickets = (mobilePayload['tickets'] ?? []) as List<dynamic>;

    final travelStatus = (mobilePayload['journeyStatus'] ?? 'unknown')
        .toString();
    final art9 = (mobilePayload['art9'] ?? {}) as Map<String, dynamic>;

    final firstTicket = tickets.isNotEmpty
        ? (tickets.first as Map<String, dynamic>)
        : <String, dynamic>{};

    // Compose ticket price with currency to match pipeline parsing
    final priceStr = (firstTicket['price'] ?? '').toString();
    final currency = (firstTicket['currency'] ?? '').toString().toUpperCase();
    final priceValue = priceStr.isEmpty ? '0' : priceStr;
    final ticketPrice = <String, dynamic>{
      'value': currency.isNotEmpty ? '$priceValue $currency' : priceValue,
      'currency': currency.isNotEmpty ? currency : 'EUR',
    };

    // Build a minimal single-leg structure for the pipeline
    final depIso = (firstTicket['depTime'] ?? '').toString();
    final arrIso = (firstTicket['arrTime'] ?? '').toString();
    final segments = <Map<String, dynamic>>[
      {
        if (depIso.isNotEmpty) 'schedDep': depIso,
        if (arrIso.isNotEmpty) 'schedArr': arrIso,
        'from': (firstTicket['from'] ?? '').toString(),
        'to': (firstTicket['to'] ?? '').toString(),
        'operator': (firstTicket['operator'] ?? '').toString(),
        'product': (firstTicket['ticketType'] ?? '').toString(),
      },
    ];

    final pipelineJourney = <String, dynamic>{
      'operator': (firstTicket['operator'] ?? '').toString(),
      'ticketPrice': ticketPrice,
      'throughTicket': (firstTicket['throughTicket'] ?? false) == true,
      'segments': segments,
      'travelStatus': travelStatus,
      'pmr': art9['pmr'],
      'bike': art9['bicycle'],
      'downgradedTicket': firstTicket['downgraded'],
    };

    // Wizard/meta mapping (step 4/5)
    final wizard = <String, dynamic>{
      'step4_art18': art18,
      'step5_assistance': {
        'got_meals': art20['meal'] ?? false,
        'got_hotel': art20['hotel'] ?? false,
        'got_transport': art20['transport'] ?? false,
        'self_paid_meals': art20['self_paid_meals'] ?? false,
        'self_paid_hotel': art20['self_paid_hotel'] ?? false,
        'self_paid_transport': art20['self_paid_transport'] ?? false,
        'hotel_needed_even_if_not_offered':
            art20['hotel_needed_even_if_not_offered'] ?? false,
        'alt_transport_offered': art20['alt_transport_offered'] ?? false,
        'blocked_train_transport': art20['blocked_train_transport'] ?? false,
        'own_expenses': art20['own_expenses'] ?? false,
        'delay_confirmation': art20['delay_confirmation'] ?? false,
        'extraordinary': art20['extraordinary'] ?? 'unknown',
        'extraordinary_type': art20['extraordinary_type'] ?? '',
        'needs_wheelchair': art20['needs_wheelchair'] ?? false,
        'needs_escort': art20['needs_escort'] ?? false,
        'other_needs': art20['other_needs'] ?? '',
      },
      'art20': art20,
    };

    // Compute overrides drive compensation minutes in pipeline
    final delayConfirmed = incident['delay_confirmed_minutes'];
    final delayExpected = incident['delay_expected_minutes'];
    final delayMinEU = (delayConfirmed ?? delayExpected ?? 0) as int? ?? 0;
    final art18Option = _mapChoiceToArt18Option(compensation['choice']);
    final compute = <String, dynamic>{
      'euOnly': true,
      'delayMinEU': delayMinEU,
      'throughTicket': (firstTicket['throughTicket'] ?? true) == true,
      if (art18Option != null) 'art18Option': art18Option,
      'refundAlready': false,
      'missedConnection': (incident['missed_connection'] ?? false) == true,
    };

    final body = <String, dynamic>{
      'journey': pipelineJourney,
      'wizard': wizard,
      'compute': compute,
      'art18': art18,
      'art20': art20,
      'event': incident,
      'assistance': art20,
      'tickets': tickets,
    };

    return _client.post('/api/pipeline/run', body);
  }

  String? _mapChoiceToArt18Option(dynamic choice) {
    final c = (choice ?? '').toString();
    if (c.isEmpty) return null;
    switch (c) {
      case 'refund':
        return 'refund';
      case 'reroute_now':
        return 'reroute_now';
      case 'reroute_later':
        return 'reroute_later';
      default:
        return null;
    }
  }
}
