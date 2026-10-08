import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists and broadcasts the user's appearance preference.
///
/// Kept separate from [SessionController] because the theme has to apply to
/// the login screen too, which is rendered before a session exists.
class ThemeController extends ChangeNotifier {
  ThemeController();

  static const String _key = 'appearance_mode';

  ThemeMode _mode = ThemeMode.dark;

  /// Dark is the designed default; light is the ivory alternative.
  ThemeMode get mode => _mode;

  bool get isDark => _mode == ThemeMode.dark;

  /// Restores the saved preference. Safe to call once during startup.
  Future<void> boot() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? stored = prefs.getString(_key);

    _mode = switch (stored) {
      'light' => ThemeMode.light,
      'system' => ThemeMode.system,
      _ => ThemeMode.dark,
    };

    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    if (_mode == mode) {
      return;
    }

    _mode = mode;

    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, mode.name);

    notifyListeners();
  }

  /// Convenience for the segmented control: cycles light then dark.
  Future<void> toggle() =>
      setMode(_mode == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark);
}
