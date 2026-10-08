import '../constants/app_constants.dart';

/// Holds the API base URL currently in use.
///
/// The value is resolved in this order: the address saved on the device, then
/// a platform specific loopback. The settings page can override it at runtime,
/// which matters because the server runs on a laptop whose LAN address
/// changes whenever the router hands out a new DHCP lease.
class ApiConfig {
  ApiConfig({String? baseUrl}) : _baseUrl = _normalize(baseUrl);

  String? _baseUrl;

  /// The server root, always without a trailing slash.
  ///
  /// Falls back to the address compiled into [AppConstants.defaultBaseUrl], so
  /// a fresh install talks to the configured server without the employee
  /// having to type anything. The settings page can point it elsewhere.
  String get baseUrl => _baseUrl ?? defaultBaseUrl;

  /// The build time default shown before the user changes anything.
  String get defaultBaseUrl => AppConstants.defaultBaseUrl;

  /// True when the server address differs from the shipped default.
  bool get isOverridden =>
      _baseUrl != null && _baseUrl != AppConstants.defaultBaseUrl;

  void setBaseUrl(String? value) {
    _baseUrl = _normalize(value);
  }

  void reset() {
    _baseUrl = null;
  }

  /// Returns a normalized address, or `null` when the input is not usable.
  static String? tryParse(String? raw) {
    final String? value = _normalize(raw);

    if (value == null) {
      return null;
    }

    final Uri? uri = Uri.tryParse(value);

    if (uri == null ||
        (uri.scheme != 'http' && uri.scheme != 'https') ||
        uri.host.isEmpty) {
      return null;
    }

    return value;
  }

  static String? _normalize(String? raw) {
    final String trimmed = (raw ?? '').trim();

    if (trimmed.isEmpty) {
      return null;
    }

    return trimmed.endsWith('/')
        ? trimmed.substring(0, trimmed.length - 1)
        : trimmed;
  }
}
