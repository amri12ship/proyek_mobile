import 'package:flutter/foundation.dart';

import '../constants/app_constants.dart';
import '../models/app_user.dart';
import '../network/api_client.dart';
import '../network/api_config.dart';
import '../network/api_exception.dart';
import '../services/attendance_repository.dart';
import '../storage/session_store.dart';

/// Owns everything global: the API configuration and the current session.
///
/// The whole app rebuilds from this single object, so a sign-in, sign-out, or
/// server change is visible everywhere without extra plumbing.
class SessionController extends ChangeNotifier {
  SessionController({
    required SessionStore store,
    required ApiConfig config,
    required ApiClient client,
    required AttendanceRepository repository,
  }) : _store = store,
       _config = config,
       _client = client,
       _repository = repository {
    _client.onUnauthorized = handleUnauthorized;
  }
  final SessionStore _store;
  final ApiConfig _config;
  final ApiClient _client;
  final AttendanceRepository _repository;

  bool _booting = true;
  bool _submitting = false;
  String? _error;
  AppUser? _user;

  /// True while the saved session is being restored at startup.
  bool get isBooting => _booting;

  /// True while a sign-in or sign-out request is in flight.
  bool get isSubmitting => _submitting;

  /// The last recoverable error message, cleared by [clearError].
  String? get error => _error;

  AppUser? get user => _user ?? _store.user;

  bool get isAuthenticated => user != null && (_store.token ?? '').isNotEmpty;

  String get baseUrl => _config.baseUrl;

  String get defaultBaseUrl => AppConstants.defaultBaseUrl;

  bool get isBaseUrlOverridden => _config.isOverridden;

  /// Restores a saved token, token, and server address from disk.
  Future<void> boot() async {
    await _store.load();

    _config.setBaseUrl(_store.baseUrl);
    _client.setToken(_store.token);

    if (_store.hasSession) {
      _user = _store.user;

      try {
        _user = await _repository.me();
      } on ApiException {
        // A dead token is handled by onUnauthorized, which clears the session.
        if (isAuthenticated) {
          _user = _store.user;
        }
      }
    }

    _booting = false;
    notifyListeners();
  }

  Future<bool> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    _submitting = true;
    _error = null;
    notifyListeners();

    try {
      _user = await _repository.login(
        email: email.trim(),
        password: password,
        deviceName: deviceName,
      );

      return true;
    } on ApiException catch (error) {
      _error = error.message;
      return false;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _submitting = true;
    notifyListeners();

    await _repository.logout();

    _user = null;
    _submitting = false;
    notifyListeners();
  }

  /// Points the app at another server, for example when the laptop's LAN
  /// address changes.
  Future<bool> updateBaseUrl(String raw) async {
    final String? parsed = ApiConfig.tryParse(raw);

    if (parsed == null) {
      _error = 'Alamat server tidak valid. Contoh: http://192.168.1.10:8000';
      notifyListeners();
      return false;
    }

    _config.setBaseUrl(parsed);
    _client.setToken(null);
    await _store.saveBaseUrl(parsed);
    await _store.clear();

    _user = null;
    _error = null;
    notifyListeners();

    return true;
  }

  Future<void> resetBaseUrl() async {
    _config.reset();
    _client.setToken(null);
    await _store.saveBaseUrl(null);
    await _store.clear();

    _user = null;
    _error = null;
    notifyListeners();
  }

  void clearError() {
    if (_error == null) {
      return;
    }

    _error = null;
    notifyListeners();
  }

  /// Called by [ApiClient] when the server rejects the stored token.
  void handleUnauthorized() {
    if (_user == null && (_store.token ?? '').isEmpty) {
      return;
    }

    _client.setToken(null);
    _store.clear();
    _user = null;
    _error = 'Sesi Anda berakhir. Silakan masuk kembali.';
    notifyListeners();
  }

  @override
  void dispose() {
    _client.onUnauthorized = null;
    super.dispose();
  }
}
