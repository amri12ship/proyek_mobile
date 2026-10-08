import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/app_user.dart';
import 'token_store.dart';

/// Persists the session (token plus the signed in user) and the server address.
///
/// The token goes through a [TokenStore] so it lands in the platform keystore,
/// everything else goes into shared preferences. A sign-out clears both, so a
/// revoked token can never be replayed from the device.
class SessionStore {
  SessionStore({TokenStore? tokenStore})
    : _tokens = tokenStore ?? const SecureTokenStore();

  static const String _userKey = 'absensi_user';
  static const String _baseUrlKey = 'absensi_base_url';
  static const String _deviceTokenKey = 'absensi_device_token';

  final TokenStore _tokens;

  String? _token;
  AppUser? _user;
  String? _baseUrl;

  String? get token => _token;

  AppUser? get user => _user;

  String? get baseUrl => _baseUrl;

  bool get hasSession => (_token ?? '').isNotEmpty && _user != null;

  /// Loads whatever survived the previous app run.
  Future<void> load() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();

    _token = await _tokens.read();
    _baseUrl = prefs.getString(_baseUrlKey);
    _user = _decodeUser(prefs.getString(_userKey));
  }

  Future<void> saveSession({
    required String token,
    required AppUser user,
  }) async {
    _token = token;
    _user = user;

    await _tokens.write(token);

    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _userKey,
      jsonEncode(<String, dynamic>{
        'id': user.id,
        'name': user.name,
        'email': user.email,
        'role': user.role,
        'nik': user.nik,
        'phone': user.phone,
        'department': user.department,
        'position': user.position,
        'photo': user.photo,
      }),
    );
  }

  Future<void> saveBaseUrl(String? value) async {
    _baseUrl = value;

    final SharedPreferences prefs = await SharedPreferences.getInstance();

    if (value == null || value.isEmpty) {
      await prefs.remove(_baseUrlKey);
    } else {
      await prefs.setString(_baseUrlKey, value);
    }
  }

  Future<String?> readDeviceToken() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();

    return prefs.getString(_deviceTokenKey);
  }

  Future<void> saveDeviceToken(String token) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();

    await prefs.setString(_deviceTokenKey, token);
  }

  Future<void> clear() async {
    _token = null;
    _user = null;

    await _tokens.clear();

    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.remove(_userKey);
  }

  static AppUser? _decodeUser(String? raw) {
    if (raw == null || raw.isEmpty) {
      return null;
    }

    try {
      return AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }
}
