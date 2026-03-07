import 'package:shared_preferences/shared_preferences.dart';

class CommuterProfile {
  final bool enabled;
  final String operatorName;
  final String operatorCountry;
  final String productName;
  final String routeName;

  const CommuterProfile({
    required this.enabled,
    required this.operatorName,
    required this.operatorCountry,
    required this.productName,
    required this.routeName,
  });

  factory CommuterProfile.empty() {
    return const CommuterProfile(
      enabled: false,
      operatorName: '',
      operatorCountry: '',
      productName: '',
      routeName: '',
    );
  }

  bool get isConfigured =>
      operatorName.trim().isNotEmpty &&
      operatorCountry.trim().isNotEmpty &&
      productName.trim().isNotEmpty &&
      routeName.trim().isNotEmpty;

  CommuterProfile copyWith({
    bool? enabled,
    String? operatorName,
    String? operatorCountry,
    String? productName,
    String? routeName,
  }) {
    return CommuterProfile(
      enabled: enabled ?? this.enabled,
      operatorName: operatorName ?? this.operatorName,
      operatorCountry: operatorCountry ?? this.operatorCountry,
      productName: productName ?? this.productName,
      routeName: routeName ?? this.routeName,
    );
  }
}

class CommuterProfileStore {
  static const _enabledKey = 'commuter_enabled';
  static const _operatorKey = 'commuter_operator';
  static const _countryKey = 'commuter_country';
  static const _productKey = 'commuter_product';
  static const _routeKey = 'commuter_route';

  Future<CommuterProfile> load() async {
    final prefs = await SharedPreferences.getInstance();
    return CommuterProfile(
      enabled: prefs.getBool(_enabledKey) ?? false,
      operatorName: prefs.getString(_operatorKey) ?? '',
      operatorCountry: prefs.getString(_countryKey) ?? '',
      productName: prefs.getString(_productKey) ?? '',
      routeName: prefs.getString(_routeKey) ?? '',
    );
  }

  Future<void> save(CommuterProfile profile) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_enabledKey, profile.enabled);
    await prefs.setString(_operatorKey, profile.operatorName);
    await prefs.setString(_countryKey, profile.operatorCountry);
    await prefs.setString(_productKey, profile.productName);
    await prefs.setString(_routeKey, profile.routeName);
  }
}
