/// Every failure surfaced by the API layer.
///
/// The Laravel backend always answers errors with
/// `{"message": "...", "code": "SOME_CODE"}`, so the client can react to the
/// machine readable `code` instead of parsing Indonesian sentences.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.code,
    this.statusCode,
    this.errors = const <String, List<String>>{},
    this.cause,
  });

  /// Builds an exception from a decoded JSON error body.
  factory ApiException.fromJson(Map<String, dynamic> body, int statusCode) {
    final Object? code = body['code'];
    final Object? message = body['message'];
    final Object? errors = body['errors'];

    return ApiException(
      message: message is String && message.isNotEmpty
          ? message
          : 'Permintaan gagal (HTTP $statusCode).',
      code: code is String ? code : null,
      statusCode: statusCode,
      errors: _normalizeErrors(errors),
    );
  }

  /// Builds an exception for a transport level problem: no server, timeout,
  /// wrong address, or a device that is offline.
  factory ApiException.network(Object cause) {
    return ApiException(
      message:
          'Tidak dapat terhubung ke server. Periksa alamat server dan '
          'koneksi Wi-Fi Anda.',
      code: 'NETWORK_UNREACHABLE',
      errors: <String, List<String>>{
        'connection': <String>['$cause'],
      },
    );
  }

  final String message;
  final String? code;
  final int? statusCode;
  final Map<String, List<String>> errors;

  /// The original platform error, kept for logging only. Never shown to the
  /// user, because it is in English and does not say what to do next.
  final Object? cause;

  /// The token expired or was revoked, so the user must sign in again.
  bool get isUnauthenticated => statusCode == 401;

  /// The session is valid but the account or the request is not allowed.
  bool get isForbidden => statusCode == 403;

  /// The session is still valid but the business rule rejected the action.
  bool get isValidation => statusCode == 422;

  /// The request never reached the server.
  bool get isNetwork => code == 'NETWORK_UNREACHABLE';

  @override
  String toString() => message;
}

Map<String, List<String>> _normalizeErrors(Object? errors) {
  if (errors is! Map<String, dynamic>) {
    return const <String, List<String>>{};
  }

  return errors.map(
    (String key, Object? value) => MapEntry<String, List<String>>(
      key,
      value is List<Object?>
          ? value.map((Object? e) => '$e').toList()
          : <String>['$value'],
    ),
  );
}
