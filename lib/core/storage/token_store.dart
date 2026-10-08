import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Storage for the bearer token.
///
/// The app never talks to `FlutterSecureStorage` directly: keeping the
/// dependency behind this interface means a hanging or unavailable keystore
/// can be swapped for a different implementation, and it keeps the session
/// logic testable without a platform channel.
abstract interface class TokenStore {
  /// Returns the stored token, or `null` when there is none.
  Future<String?> read();

  /// Persists the token, replacing any previous value.
  Future<void> write(String token);

  /// Removes the token.
  Future<void> clear();
}

/// [TokenStore] backed by the platform keystore (Keychain on iOS, EncryptedSharedPreferences
/// on Android). This is the implementation used by the app.
class SecureTokenStore implements TokenStore {
  const SecureTokenStore({this.key = 'absensi_access_token'});

  final String key;

  @override
  Future<String?> read() => const FlutterSecureStorage().read(key: key);

  @override
  Future<void> write(String token) =>
      const FlutterSecureStorage().write(key: key, value: token);

  @override
  Future<void> clear() => const FlutterSecureStorage().delete(key: key);
}

/// [TokenStore] that keeps the token in memory only.
///
/// Used as a fallback when the keystore is unusable, so a device with a broken
/// secure storage still gets a working (if non-persistent) session instead of a
/// permanently stuck splash screen.
class MemoryTokenStore implements TokenStore {
  String? _token;

  @override
  Future<String?> read() async => _token;

  @override
  Future<void> write(String token) async {
    _token = token;
  }

  @override
  Future<void> clear() async {
    _token = null;
  }
}
