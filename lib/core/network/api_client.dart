import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../constants/app_constants.dart';
import 'api_config.dart';
import 'api_exception.dart';

/// Thin wrapper around `package:http` that speaks the backend contract:
/// JSON in, JSON out, `Authorization: Bearer <token>` on every call except
/// login, and every failure converted into an [ApiException].
class ApiClient {
  ApiClient({required ApiConfig config, http.Client? httpClient})
    : _config = config,
      _http = httpClient ?? http.Client();
  final ApiConfig _config;
  final http.Client _http;

  String? _token;

  /// Called when the server reports an expired or revoked token so the app can
  /// drop the session and send the user back to the login page.
  void Function()? onUnauthorized;

  String? get token => _token;

  void setToken(String? token) {
    _token = token;
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String>? query,
    Duration? timeout,
  }) {
    return _send(
      () => _http.get(_uri(path, query), headers: _headers()),
      timeout,
    );
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    Duration? timeout,
  }) {
    return _send(
      () => _http.post(
        _uri(path),
        headers: _headers(json: true),
        body: jsonEncode(body ?? const <String, dynamic>{}),
      ),
      timeout,
    );
  }

  Future<Map<String, dynamic>> upload(
    String path, {
    required String fileField,
    required File file,
    Map<String, String> fields = const <String, String>{},
  }) async {
    try {
      final http.MultipartRequest request =
          http.MultipartRequest('POST', _uri(path))
            ..headers.addAll(_headers())
            ..fields.addAll(fields)
            ..files.add(
              await http.MultipartFile.fromPath(fileField, file.path),
            );

      final http.StreamedResponse streamed = await _http
          .send(request)
          .timeout(AppConstants.uploadTimeout);

      return await _decodeStreamed(streamed);
    } on ApiException {
      rethrow;
    } on FileSystemException catch (error) {
      throw ApiException(
        message: 'Berkas foto tidak ditemukan atau tidak dapat dibaca.',
        code: 'FILE_UNAVAILABLE',
        cause: error,
      );
    } on TimeoutException catch (error) {
      throw ApiException.network(error);
    } on SocketException catch (error) {
      throw ApiException.network(error.message);
    } on http.ClientException catch (error) {
      throw ApiException.network(error.message);
    }
  }

  void close() {
    _http.close();
  }

  Future<Map<String, dynamic>> _send(
    Future<http.Response> Function() request,
    Duration? timeout,
  ) async {
    try {
      final http.Response response = await request().timeout(
        timeout ?? AppConstants.requestTimeout,
      );

      return _decode(response);
    } on ApiException {
      rethrow;
    } on TimeoutException catch (error) {
      throw ApiException.network(error);
    } on SocketException catch (error) {
      throw ApiException.network(error.message);
    } on http.ClientException catch (error) {
      throw ApiException.network(error.message);
    } on FormatException catch (error) {
      throw ApiException(
        message: 'Respons server tidak dapat dibaca.',
        code: 'INVALID_RESPONSE',
        errors: <String, List<String>>{
          'response': <String>['$error'],
        },
      );
    }
  }

  Map<String, dynamic> _decode(http.Response response) {
    final String body = response.body;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (body.isEmpty) {
        return <String, dynamic>{};
      }

      final Object? decoded = jsonDecode(body);

      if (decoded is Map<String, dynamic>) {
        return decoded;
      }

      return <String, dynamic>{'data': decoded};
    }

    if (response.statusCode == 401) {
      onUnauthorized?.call();
    }

    throw ApiException.fromJson(_safeJson(body), response.statusCode);
  }

  Future<Map<String, dynamic>> _decodeStreamed(
    http.StreamedResponse response,
  ) async {
    final String body = await response.stream.bytesToString();
    final Map<String, dynamic> decoded = _safeJson(body);

    if (response.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException.fromJson(decoded, 401);
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException.fromJson(decoded, response.statusCode);
    }

    return decoded;
  }

  Uri _uri(String path, [Map<String, String>? query]) {
    return Uri.parse(
      '${_config.baseUrl}$path',
    ).replace(queryParameters: (query == null || query.isEmpty) ? null : query);
  }

  Map<String, String> _headers({bool json = false}) {
    return <String, String>{
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    };
  }

  static Map<String, dynamic> _safeJson(String body) {
    if (body.isEmpty) {
      return <String, dynamic>{};
    }

    try {
      final Object? decoded = jsonDecode(body);

      return decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'message': '$decoded'};
    } on FormatException {
      return <String, dynamic>{'message': body};
    }
  }
}
