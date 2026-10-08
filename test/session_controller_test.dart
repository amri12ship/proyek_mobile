import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:proyek_mobile/core/models/app_user.dart';
import 'package:proyek_mobile/core/network/api_client.dart';
import 'package:proyek_mobile/core/network/api_config.dart';
import 'package:proyek_mobile/core/services/attendance_repository.dart';
import 'package:proyek_mobile/core/state/session_controller.dart';
import 'package:proyek_mobile/core/storage/session_store.dart';
import 'package:proyek_mobile/core/storage/token_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late SessionStore store;
  late List<http.BaseRequest> sent;

  SessionController controllerReturning(
    Future<http.Response> Function(http.Request request) handler,
  ) {
    final ApiClient client = ApiClient(
      config: ApiConfig(baseUrl: 'http://10.0.0.5:8000'),
      httpClient: MockClient((http.Request request) {
        sent.add(request);

        return handler(request);
      }),
    );

    return SessionController(
      store: store,
      config: ApiConfig(baseUrl: 'http://10.0.0.5:8000'),
      client: client,
      repository: AttendanceRepository(client: client, store: store),
    );
  }

  http.Response loginOk({String email = 'budi@absensi.test'}) {
    return http.Response(
      jsonEncode(<String, dynamic>{
        'message': 'Login berhasil.',
        'token_type': 'Bearer',
        'access_token': 'token-xyz',
        'user': <String, dynamic>{
          'id': 2,
          'name': 'Budi Santoso',
          'email': email,
          'role': 'employee',
          'employee': <String, dynamic>{'nik': 'EMP-002', 'department': 'IT'},
        },
      }),
      200,
    );
  }

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
    store = SessionStore(tokenStore: MemoryTokenStore());
    sent = <http.BaseRequest>[];
  });

  test('boot completes and reports no session on a fresh install', () async {
    final SessionController controller = controllerReturning(
      (_) async => http.Response('{}', 200),
    );

    await controller.boot();

    expect(controller.isBooting, isFalse);
    expect(controller.isAuthenticated, isFalse);
    expect(controller.user, isNull);
  });

  test(
    'a successful sign-in stores the session and exposes the user',
    () async {
      final SessionController controller = controllerReturning((
        http.Request request,
      ) async {
        if (request.url.path == '/api/v1/device') {
          return http.Response('{"data":{"id":1}}', 201);
        }

        return loginOk();
      });

      await controller.boot();

      final bool ok = await controller.login(
        email: ' budi@absensi.test ',
        password: 'password123',
        deviceName: 'Android',
      );

      expect(ok, isTrue);
      expect(controller.isAuthenticated, isTrue);
      expect(controller.user?.name, 'Budi Santoso');
      expect(controller.user?.department, 'IT');
      expect(controller.error, isNull);
      expect(store.token, 'token-xyz');
      expect(sent.first.url.path, '/api/v1/auth/login');
      expect(
        jsonDecode((sent.first as http.Request).body)['email'],
        'budi@absensi.test',
        reason: 'the email must be trimmed before it is sent',
      );
    },
  );

  test(
    'a rejected sign-in surfaces the server message and keeps no session',
    () async {
      final SessionController controller = controllerReturning(
        (_) async => http.Response(
          jsonEncode(<String, dynamic>{
            'message': 'Email atau password salah.',
            'code': 'INVALID_CREDENTIALS',
          }),
          401,
        ),
      );

      await controller.boot();

      final bool ok = await controller.login(
        email: 'budi@absensi.test',
        password: 'salah',
        deviceName: 'Android',
      );

      expect(ok, isFalse);
      expect(controller.error, 'Email atau password salah.');
      expect(controller.isAuthenticated, isFalse);
      expect(controller.isSubmitting, isFalse);
    },
  );

  test('logout revokes the token server side and clears the session', () async {
    final SessionController controller = controllerReturning((
      http.Request request,
    ) async {
      if (request.url.path == '/api/v1/auth/logout') {
        return http.Response('{"message":"Logout berhasil."}', 200);
      }

      return loginOk();
    });

    await controller.boot();
    await controller.login(
      email: 'budi@absensi.test',
      password: 'password123',
      deviceName: 'Android',
    );

    await controller.logout();

    expect(controller.isAuthenticated, isFalse);
    expect(store.token, isNull);
    expect(
      sent.map((http.BaseRequest r) => r.url.path),
      contains('/api/v1/auth/logout'),
    );
  });

  test(
    'an expired token drops the session and asks the user to sign in',
    () async {
      final SessionController controller = controllerReturning((
        http.Request request,
      ) async {
        if (request.url.path == '/api/v1/me') {
          return http.Response('{"message":"Unauthenticated."}', 401);
        }

        return loginOk();
      });

      await controller.boot();
      await controller.login(
        email: 'budi@absensi.test',
        password: 'password123',
        deviceName: 'Android',
      );

      controller.handleUnauthorized();

      expect(controller.isAuthenticated, isFalse);
      expect(controller.error, contains('Sesi Anda berakhir'));
    },
  );

  test('the server address can be changed and is persisted', () async {
    final SessionController controller = controllerReturning(
      (_) async => http.Response('{}', 200),
    );

    await controller.boot();

    final bool ok = await controller.updateBaseUrl('http://192.168.1.50:8000/');

    expect(ok, isTrue);
    expect(controller.baseUrl, 'http://192.168.1.50:8000');
    expect(controller.isBaseUrlOverridden, isTrue);
    expect(store.baseUrl, 'http://192.168.1.50:8000');
  });

  test('an invalid server address is rejected with guidance', () async {
    final SessionController controller = controllerReturning(
      (_) async => http.Response('{}', 200),
    );

    await controller.boot();

    final bool ok = await controller.updateBaseUrl('bukan-url');

    expect(ok, isFalse);
    expect(controller.error, contains('Alamat server tidak valid'));
  });

  test('changing the server address signs the user out on purpose', () async {
    final SessionController controller = controllerReturning(
      (_) async => loginOk(),
    );

    await controller.boot();
    await controller.login(
      email: 'budi@absensi.test',
      password: 'password123',
      deviceName: 'Android',
    );
    await controller.updateBaseUrl('http://192.168.1.50:8000');

    expect(controller.isAuthenticated, isFalse);
    expect(store.token, isNull);
  });

  test('a saved session is restored on the next launch', () async {
    final AppUser user = AppUser.fromJson(<String, dynamic>{
      'id': 2,
      'name': 'Budi Santoso',
      'email': 'budi@absensi.test',
      'role': 'employee',
    });
    await store.saveSession(token: 'token-xyz', user: user);

    final SessionController controller = controllerReturning((
      http.Request request,
    ) async {
      if (request.url.path == '/api/v1/me') {
        return http.Response(
          jsonEncode(<String, dynamic>{
            'data': <String, dynamic>{
              'id': 2,
              'name': 'Budi Santoso',
              'email': 'budi@absensi.test',
              'role': 'employee',
            },
          }),
          200,
        );
      }

      return http.Response('{}', 200);
    });

    await controller.boot();

    expect(controller.isAuthenticated, isTrue);
    expect(controller.user?.email, 'budi@absensi.test');
  });
}
