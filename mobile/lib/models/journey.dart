class Journey {
  final String id;
  final String depStation;
  final String arrStation;
  final String status;
  final int delayMinutes;

  Journey({
    required this.id,
    required this.depStation,
    required this.arrStation,
    required this.status,
    required this.delayMinutes,
  });

  factory Journey.fromJson(Map<String, dynamic> json) {
    return Journey(
      id: (json['id'] ?? '').toString(),
      depStation: (json['dep_station'] ?? json['start'] ?? '').toString(),
      arrStation: (json['arr_station'] ?? json['end'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      delayMinutes: int.tryParse((json['delay_minutes'] ?? json['delay'] ?? '0').toString()) ?? 0,
    );
  }
}
