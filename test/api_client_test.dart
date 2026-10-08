import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:proyek_mobile/core/constants/app_constants.dart';
import 'package:proyek_mobile/core/network/api_client.dart';
import 'package:proyek_mobile/core/network/api_config.dart';
import 'package:proyek_mobile/core/network/api_exception.dart';

void main() {
  late ApiConfig config;
  late List<http.BaseRequest> sent;

  ApiClient clientReturning(Object handler, {void Function()? onUnauthorized}) {
    final MockClient mock = MockClient((http.Request request) async {
      return handler as http.Response;
    });

    final ApiClient client = ApiClient(config: config, httpClient: mock);
    client.onUnauthorized = onUnauthorized;

    return client;
  }

  setUp(() {
    config = ApiConfig(baseUrl: 'http://10.0.0.5:8000');
    sent = <http.BaseRequest>[];
  });

  group('ApiConfig', () {
    test('normalises a trailing slash', () {
      expect(
        ApiConfig(baseUrl: 'http://10.0.0.5:8000/').baseUrl,
        'http://10.0.0.5:8000',
      );
    });

    test('rejects an unusable address', () {
      expect(ApiConfig.tryParse('bukan-url'), isNull);
      expect(ApiConfig.tryParse('ftp://10.0.0.5'), isNull);
      expect(ApiConfig.tryParse(''), isNull);
      expect(ApiConfig.tryParse(null), isNull);
    });

    test('accepts http and https addresses', () {
      expect(
        ApiConfig.tryParse('http://192.168.1.10:8000'),
        'http://192.168.1.10:8000',
      );
      expect(
        ApiConfig.tryParse('https://absensi.example.com'),
        'https://absensi.example.com',
      );
    });

    test('falls back to the compiled default when nothing is stored', () {
      final ApiConfig empty = ApiConfig();

      expect(empty.baseUrl, AppConstants.defaultBaseUrl);
      expect(empty.isOverridden, isFalse);
    });

    test('resets back to the compiled default', () {
      final ApiConfig config = ApiConfig(baseUrl: 'http://192.168.1.50:8000');
      expect(config.isOverridden, isTrue);

      config.reset();

      expect(config.baseUrl, AppConstants.defaultBaseUrl);
      expect(config.isOverridden, isFalse);
    });
  });

  group('ApiClient', () {
    test('sends the bearer token and returns the decoded body', () async {
      final MockClient mock = MockClient((http.Request request) async {
        sent.add(request);

        return http.Response(
          jsonEncode(<String, dynamic>{
            'data': <String, dynamic>{'id': 7},
          }),
          200,
          headers: <String, String>{'content-type': 'application/json'},
        );
      });

      final ApiClient client = ApiClient(config: config, httpClient: mock)
        ..setToken('token-abc');

      final Map<String, dynamic> body = await client.get(
        '/api/v1/me',
        query: <String, String>{'a': 'b'},
      );

      expect(body['data'], <String, dynamic>{'id': 7});
      expect(sent.single.headers['Authorization'], 'Bearer token-abc');
      expect(sent.single.url.path, '/api/v1/me');
      expect(sent.single.url.queryParameters, <String, String>{'a': 'b'});
    });

    test('omits the authorization header when there is no token', () async {
      final MockClient mock = MockClient((http.Request request) async {
        sent.add(request);

        return http.Response('{}', 200);
      });

      final ApiClient client = ApiClient(config: config, httpClient: mock);
      await client.post('/api/v1/auth/login', body: <String, dynamic>{'a': 1});

      expect(sent.single.headers.containsKey('Authorization'), isFalse);
    });

    test('maps a business error onto the server code and message', () async {
      final ApiClient client = clientReturning(
        http.Response(
          jsonEncode(<String, dynamic>{
            'message': 'Anda sudah check-in hari ini.',
            'code': 'ALREADY_CHECKED_IN',
          }),
          422,
        ),
      );

      await expectLater(
        client.post('/api/v1/attendance/check-in'),
        throwsA(
          isA<ApiException>()
              .having((ApiException e) => e.code, 'code', 'ALREADY_CHECKED_IN')
              .having(
                (ApiException e) => e.message,
                'message',
                'Anda sudah check-in hari ini.',
              )
              .having(
                (ApiException e) => e.isValidation,
                'isValidation',
                isTrue,
              ),
        ),
      );
    });

    test('notifies the listener and flags the error on HTTP 401', () async {
      bool notified = false;

      final ApiClient client = clientReturning(
        http.Response(
          jsonEncode(<String, dynamic>{'message': 'Unauthenticated.'}),
          401,
        ),
        onUnauthorized: () => notified = true,
      );

      await expectLater(
        client.get('/api/v1/me'),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.isUnauthenticated,
            'isUnauthenticated',
            isTrue,
          ),
        ),
      );

      expect(notified, isTrue);
    });

    test('turns a transport failure into a network error', () async {
      final MockClient mock = MockClient((http.Request request) async {
        throw const SocketException('Connection refused');
      });

      final ApiClient client = ApiClient(config: config, httpClient: mock);

      await expectLater(
        client.get('/api/v1/me'),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.isNetwork,
            'isNetwork',
            isTrue,
          ),
        ),
      );
    });

    test('reports an unreadable success body instead of crashing', () async {
      final ApiClient client = clientReturning(
        http.Response('not json at all', 200),
      );

      await expectLater(
        client.get('/api/v1/me'),
        throwsA(
          isA<ApiException>().having(
            (ApiException e) => e.code,
            'code',
            'INVALID_RESPONSE',
          ),
        ),
      );
    });

    test('uploads a selfie as multipart form data', () async {
      final File file = File(
        '${Directory.systemTemp.path}/absensi_selfie_test.jpg',
      );
      await file.writeAsBytes(<int>[1, 2, 3, 4]);

      addTearDown(() async {
        if (file.existsSync()) {
          await file.delete();
        }
      });

      final MockClient mock = MockClient((http.Request request) async {
        sent.add(request);

        return http.Response(
          jsonEncode(<String, dynamic>{
            'data': <String, dynamic>{'path': 'selfies/2/abc.jpg'},
          }),
          201,
        );
      });

      final ApiClient client = ApiClient(config: config, httpClient: mock);
      final Map<String, dynamic> body = await client.upload(
        '/api/v1/attendance/selfie',
        fileField: 'selfie',
        file: file,
      );

      expect(
        (body['data'] as Map<String, dynamic>)['path'],
        'selfies/2/abc.jpg',
      );
      expect(
        sent.single.headers['content-type'],
        contains('multipart/form-data'),
      );
      expect(sent.single.url.path, '/api/v1/attendance/selfie');
    });
  });

  group('ApiException', () {
    test('exposes the first validation message per field', () {
      final ApiException error = ApiException.fromJson(<String, dynamic>{
        'message': 'Data tidak valid.',
        'code': 'VALIDATION_FAILED',
        'errors': <String, dynamic>{
          'ticket': <Object?>['Tiket tidak dikenal.'],
        },
      }, 422);

      expect(error.code, 'VALIDATION_FAILED');
      expect(error.errors['ticket'], <String>['Tiket tidak dikenal.']);
    });

    test('falls back to a generic message when the body has none', () {
      final ApiException error = ApiException.fromJson(
        <String, dynamic>{},
        500,
      );

      expect(error.message, contains('500'));
      expect(error.isUnauthenticated, isFalse);
      expect(error.isForbidden, isFalse);
    });
  });
}
