import 'package:intl/intl.dart';

/// Date, time, and distance formatting shared by every page.
abstract final class Formatters {
  const Formatters._();

  static final DateFormat _day = DateFormat('EEEE, d MMMM yyyy', 'id');
  static final DateFormat _shortDay = DateFormat('d MMM yyyy', 'id');
  static final DateFormat _time = DateFormat('HH:mm');
  static final DateFormat _apiDay = DateFormat('yyyy-MM-dd');

  /// `Senin, 5 Oktober 2026`
  static String longDate(DateTime value) => _day.format(value);

  /// `5 Okt 2026`
  static String shortDate(DateTime value) => _shortDay.format(value);

  /// `08:12`
  static String time(DateTime? value) =>
      value == null ? '--:--' : _time.format(value);

  /// The format the API expects for `from` and `to` query parameters.
  static String apiDate(DateTime value) => _apiDay.format(value);

  /// `12 m` or `1.2 km`, depending on magnitude.
  static String distance(double? meters) {
    if (meters == null) {
      return '-';
    }

    if (meters < 1000) {
      return '${meters.round()} m';
    }

    return '${(meters / 1000).toStringAsFixed(1)} km';
  }

  /// `±8 m` accuracy, or `-` when the platform did not report it.
  static String accuracy(double? meters) {
    if (meters == null) {
      return '-';
    }

    return '±${meters.round()} m';
  }

  /// `01:59` for a countdown, never negative.
  static String countdown(Duration value) {
    final Duration safe = value.isNegative ? Duration.zero : value;
    final String minutes = safe.inMinutes.toString().padLeft(2, '0');
    final String seconds = (safe.inSeconds % 60).toString().padLeft(2, '0');

    return '$minutes:$seconds';
  }
}
