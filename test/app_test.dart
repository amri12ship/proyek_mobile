import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:proyek_mobile/app/app.dart';
import 'package:proyek_mobile/app/app_theme.dart';
import 'package:proyek_mobile/core/constants/app_constants.dart';
import 'package:proyek_mobile/features/auth/pages/login_page.dart';
import 'package:proyek_mobile/features/shell/pages/home_shell.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:proyek_mobile/core/storage/session_store.dart';
import 'package:proyek_mobile/core/storage/token_store.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  SessionStore newStore() => SessionStore(tokenStore: MemoryTokenStore());

  testWidgets('a fresh install starts on the login page', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    expect(find.byType(MaterialApp), findsOneWidget);
    expect(find.byType(LoginPage), findsOneWidget);
    expect(find.byType(HomeShell), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('the login form shows the email and password fields', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
    expect(find.text(AppConstants.appName), findsWidgets);
  });

  testWidgets('an empty form shows validation errors and stays on login', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Masuk'));
    await tester.pumpAndSettle();

    expect(find.text('Email wajib diisi.'), findsOneWidget);
    expect(find.text('Password minimal 6 karakter.'), findsOneWidget);
    expect(find.byType(LoginPage), findsOneWidget);
  });

  testWidgets('a malformed email is rejected before any request', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'bukan-email');
    await tester.enterText(find.byType(TextFormField).last, 'password123');
    await tester.tap(find.text('Masuk'));
    await tester.pumpAndSettle();

    expect(find.text('Format email tidak valid.'), findsOneWidget);
  });

  testWidgets('login does not overflow on a small android screen', (
    WidgetTester tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 568);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.byType(LoginPage), findsOneWidget);
  });

  testWidgets('login adapts to a tablet width', (WidgetTester tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(1280, 800);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(AbsensiApp(sessionStore: newStore()));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text(AppConstants.appName), findsWidgets);
  });

  test('the theme is material 3 and derived from a single seed color', () {
    expect(AppTheme.light.useMaterial3, isTrue);
    expect(AppTheme.seedColor, isA<Color>());
  });
}
