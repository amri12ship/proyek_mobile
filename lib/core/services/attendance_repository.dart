import 'dart:io';

import 'package:package_info_plus/package_info_plus.dart';

import '../models/app_user.dart';
import '../models/attendance_location.dart';
import '../models/attendance_record.dart';
import '../models/attendance_snapshot.dart';
import '../models/qr_ticket.dart';
import '../network/api_client.dart';
import '../network/api_exception.dart';
import '../storage/session_store.dart';

/// Every call the app makes against the attendance API.
///
/// Keeping the endpoints in one place means the pages only deal with domain
/// objects and never with raw JSON or path strings.
class AttendanceRepository {
  AttendanceRepository({required this._client, required this._store});

  final ApiClient _client;
  final SessionStore _store;

  Future<AppUser> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    _client.setToken(null);

    final Map<String, dynamic> body = await _client.post(
      '/api/v1/auth/login',
      body: <String, dynamic>{
        'email': email,
        'password': password,
        'device_name': deviceName,
        'platform': Platform.operatingSystem,
      },
    );

    final String token = '${body['access_token'] ?? ''}';

    if (token.isEmpty) {
      throw const ApiException(
        message: 'Server tidak mengembalikan token akses.',
        code: 'MISSING_TOKEN',
      );
    }

    final Object? user = body['user'];
    final AppUser appUser = AppUser.fromJson(
      user is Map<String, dynamic> ? user : const <String, dynamic>{},
    );

    await _store.saveSession(token: token, user: appUser);
    _client.setToken(token);

    try {
      await registerDevice(deviceName: deviceName);
    } catch (_) {
      // The device record is only used for audit and future push delivery, so
      // no failure here, including a platform that cannot report its app
      // version, may undo an otherwise successful sign-in.
    }

    return appUser;
  }

  Future<void> logout() async {
    try {
      await _client.post('/api/v1/auth/logout');
    } on ApiException {
      // Revoking the token is best effort: the local session is dropped either
      // way, and an expired token would fail the same way.
    }

    _client.setToken(null);
    await _store.clear();
  }

  Future<AppUser> me() async {
    final Map<String, dynamic> body = await _client.get('/api/v1/me');
    final Object? data = body['data'];

    return AppUser.fromJson(
      data is Map<String, dynamic> ? data : const <String, dynamic>{},
    );
  }

  Future<AttendanceSnapshot> today({
    double? latitude,
    double? longitude,
  }) async {
    final Map<String, dynamic> body = await _client.get(
      '/api/v1/attendance/today',
      query: _coordinateQuery(latitude, longitude),
    );

    final Object? data = body['data'];

    return AttendanceSnapshot.fromJson(
      data is Map<String, dynamic> ? data : const <String, dynamic>{},
    );
  }

  Future<List<AttendanceLocation>> locations({
    double? latitude,
    double? longitude,
  }) async {
    final Map<String, dynamic> body = await _client.get(
      '/api/v1/locations',
      query: _coordinateQuery(latitude, longitude),
    );

    return _listOf(body['data'])
        .map(AttendanceLocation.fromJson)
        .toList(growable: false);
  }

  Future<QrTicket> validateQr({
    required String code,
    double? latitude,
    double? longitude,
  }) async {
    final Map<String, dynamic> body = await _client.post(
      '/api/v1/qr/validate',
      body: <String, dynamic>{
        'code': code,
        'latitude': ?latitude,
        'longitude': ?longitude,
      },
    );

    final Object? data = body['data'];

    return QrTicket.fromJson(
      data is Map<String, dynamic> ? data : const <String, dynamic>{},
    );
  }

  Future<String> uploadSelfie(File file) async {
    final Map<String, dynamic> body = await _client.upload(
      '/api/v1/attendance/selfie',
      fileField: 'selfie',
      file: file,
    );

    final Object? data = body['data'];
    final String path = data is Map<String, dynamic>
        ? '${data['path'] ?? ''}'
        : '';

    if (path.isEmpty) {
      throw const ApiException(
        message: 'Server tidak menyimpan berkas selfie.',
        code: 'SELFIE_NOT_STORED',
      );
    }

    return path;
  }

  Future<AttendanceRecord> checkIn({
    required String ticket,
    required double latitude,
    required double longitude,
    double? accuracy,
    String? selfie,
  }) async {
    final Map<String, dynamic> body = await _client.post(
      '/api/v1/attendance/check-in',
      body: <String, dynamic>{
        'ticket': ticket,
        'latitude': latitude,
        'longitude': longitude,
        'accuracy': ?accuracy,
        'selfie': ?selfie,
        'device_id': await _deviceToken(),
      },
    );

    return _recordOf(body);
  }

  Future<AttendanceRecord> checkOut({
    required double latitude,
    required double longitude,
    double? accuracy,
    String? selfie,
  }) async {
    final Map<String, dynamic> body = await _client.post(
      '/api/v1/attendance/check-out',
      body: <String, dynamic>{
        'latitude': latitude,
        'longitude': longitude,
        'accuracy': ?accuracy,
        'selfie': ?selfie,
      },
    );

    return _recordOf(body);
  }

  Future<AttendanceHistory> history({
    required String from,
    required String to,
  }) async {
    final Map<String, dynamic> body = await _client.get(
      '/api/v1/attendance/history',
      query: <String, String>{'from': from, 'to': to, 'per_page': '60'},
    );

    final Object? meta = body['meta'];
    final Map<String, dynamic> metaMap = meta is Map<String, dynamic>
        ? meta
        : const <String, dynamic>{};

    return AttendanceHistory(
      records: _listOf(body['data'])
          .map(AttendanceRecord.fromJson)
          .toList(growable: false),
      total: _intOf(metaMap['total']),
    );
  }

  Future<void> registerDevice({required String deviceName}) async {
    final PackageInfo info = await PackageInfo.fromPlatform();
    final String token = await _deviceToken();

    await _client.post(
      '/api/v1/device',
      body: <String, dynamic>{
        'device_token': token,
        'device_name': deviceName,
        'platform': Platform.operatingSystem,
        'app_version': '${info.version}+${info.buildNumber}',
      },
    );
  }

  Future<String> _deviceToken() async {
    final String? stored = await _store.readDeviceToken();

    if (stored != null && stored.isNotEmpty) {
      return stored;
    }

    final String generated =
        '${Platform.operatingSystem}-${DateTime.now().microsecondsSinceEpoch}';

    await _store.saveDeviceToken(generated);

    return generated;
  }

  static AttendanceRecord _recordOf(Map<String, dynamic> body) {
    final Object? data = body['data'];

    return AttendanceRecord.fromJson(
      data is Map<String, dynamic> ? data : const <String, dynamic>{},
    );
  }

  static List<Map<String, dynamic>> _listOf(Object? value) {
    if (value is! List<Object?>) {
      return const <Map<String, dynamic>>[];
    }

    return value.whereType<Map<String, dynamic>>().toList(growable: false);
  }

  static int _intOf(Object? value) {
    if (value is num) {
      return value.round();
    }

    return int.tryParse('$value') ?? 0;
  }

  static Map<String, String>? _coordinateQuery(
    double? latitude,
    double? longitude,
  ) {
    if (latitude == null || longitude == null) {
      return null;
    }

    return <String, String>{'latitude': '$latitude', 'longitude': '$longitude'};
  }
}

/// One page of attendance history.
class AttendanceHistory {
  const AttendanceHistory({required this.records, required this.total});

  final List<AttendanceRecord> records;
  final int total;
}
