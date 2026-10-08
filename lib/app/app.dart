import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import '../core/constants/app_constants.dart';
import '../core/network/api_client.dart';
import '../core/network/api_config.dart';
import '../core/services/attendance_repository.dart';
import '../core/services/location_service.dart';
import '../core/state/attendance_controller.dart';
import '../core/state/session_controller.dart';
import '../core/state/theme_controller.dart';
import '../core/storage/session_store.dart';
import '../features/auth/pages/login_page.dart';
import '../features/shell/pages/home_shell.dart';
import 'app_routes.dart';
import 'app_theme.dart';

/// Root widget of the application.
///
/// It owns the dependency graph, restores the saved session, and decides
/// between the login screen and the signed-in shell. The rest of the app is
/// stateless with respect to wiring: pages only read the controllers from
/// `provider`.
class AbsensiApp extends StatefulWidget {
  const AbsensiApp({super.key, this.sessionStore});

  /// Overrides the persisted session storage. Production passes nothing; tests
  /// inject a store that does not depend on a platform keystore.
  final SessionStore? sessionStore;

  @override
  State<AbsensiApp> createState() => _AbsensiAppState();
}

class _AbsensiAppState extends State<AbsensiApp> {
  late final SessionStore _store = widget.sessionStore ?? SessionStore();
  late final ApiConfig _config = ApiConfig();
  late final ApiClient _client = ApiClient(config: _config);
  late final AttendanceRepository _repository = AttendanceRepository(
    client: _client,
    store: _store,
  );
  late final LocationService _locationService = LocationService();
  final ThemeController _theme = ThemeController();

  late final SessionController _session = SessionController(
    store: _store,
    config: _config,
    client: _client,
    repository: _repository,
  );
  late final AttendanceController _attendance = AttendanceController(
    repository: _repository,
    locationService: _locationService,
  );

  @override
  void initState() {
    super.initState();
    _session.boot();
    _theme.boot();
  }

  @override
  void dispose() {
    _attendance.dispose();
    _session.dispose();
    _theme.dispose();
    _client.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: <SingleChildWidget>[
        ChangeNotifierProvider<SessionController>.value(value: _session),
        ChangeNotifierProvider<AttendanceController>.value(value: _attendance),
        ChangeNotifierProvider<ThemeController>.value(value: _theme),
      ],
      child: Consumer<ThemeController>(
        builder: (BuildContext context, ThemeController theme, Widget? _) {
          return MaterialApp(
            title: AppConstants.appName,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            darkTheme: AppTheme.dark,
            themeMode: theme.mode,
            routes: AppRoutes.routes,
            home: const _AuthGate(),
          );
        },
      ),
    );
  }
}

/// Shows a spinner while the session is restored, the login form when there is
/// no usable token, and the signed-in shell otherwise.
class _AuthGate extends StatelessWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context) {
    final SessionController session = context.watch<SessionController>();

    if (session.isBooting) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (!session.isAuthenticated) {
      return const LoginPage();
    }

    return const HomeShell();
  }
}
