import 'package:flutter/material.dart';

import '../features/auth/pages/login_page.dart';

/// Route names of the application.
///
/// The signed-in shell and the login form are both decided by the auth gate in
/// `AbsensiApp`, so `/` is deliberately absent from this table: `MaterialApp`
/// forbids a `home` widget and a `/` entry at the same time. Named routes are
/// kept for the screens that can be deep linked.
abstract final class AppRoutes {
  const AppRoutes._();

  static const String login = '/login';

  static final Map<String, WidgetBuilder> routes = <String, WidgetBuilder>{
    login: (BuildContext context) => const LoginPage(),
  };
}
