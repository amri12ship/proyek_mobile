/// Central place for app-wide constants that are not related to a single feature.
abstract final class AppConstants {
  const AppConstants._();

  /// Application name. Used as the app title, the AppBar title, and the
  /// platform task label. Change it here only.
  static const String appName = 'Absensi Karyawan';

  /// Short description of the app shown on the login page.
  static const String appDescription =
      'Absen dengan scan QR lokasi dan validasi GPS.';

  /// Default API host used on a fresh install.
  ///
  /// Points at the production deployment on Laravel Cloud over HTTPS, so the
  /// app works from any network and the employee never has to type a server
  /// address. It can still be changed at runtime from the settings page and is
  /// then persisted on the device, which is what makes testing against a local
  /// server possible again.
  static const String defaultBaseUrl =
      'https://absensi-api-production-ctfthe.laravel.cloud';

  /// Timeout for regular API calls.
  static const Duration requestTimeout = Duration(seconds: 20);

  /// Timeout for multipart uploads, which are slower than plain JSON calls.
  static const Duration uploadTimeout = Duration(seconds: 45);

  /// How long the app waits for a usable GPS fix before giving up.
  static const Duration gpsTimeout = Duration(seconds: 25);

  /// Maximum width of the content column so text stays readable on tablets
  /// and desktop/web instead of stretching edge to edge.
  static const double maxContentWidth = 640;

  /// Base horizontal/vertical padding for a standard page.
  static const double pagePadding = 16;

  /// Base spacing unit used to build consistent gaps.
  static const double spacing = 16;
}
