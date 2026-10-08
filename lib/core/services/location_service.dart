import 'dart:async';

import 'package:geolocator/geolocator.dart';

import '../constants/app_constants.dart';
import '../network/api_exception.dart';

/// A usable GPS reading, already converted to the units the API expects.
class PositionFix {
  const PositionFix({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
  });

  final double latitude;
  final double longitude;
  final double accuracy;

  Map<String, dynamic> toPayload() => <String, dynamic>{
    'latitude': latitude,
    'longitude': longitude,
    'accuracy': accuracy,
  };
}

/// Wraps `geolocator` so pages never touch platform permission APIs directly.
class LocationService {
  Position? _cached;

  bool get hasCachedFix => _cached != null;

  /// Requests permission if needed and returns a fresh position.
  ///
  /// Throws an [ApiException] with a message that is safe to show to the user
  /// when the permission is denied, the service is off, or no fix arrives.
  Future<PositionFix> currentFix({bool highAccuracy = true}) async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const ApiException(
        message:
            'Layanan lokasi sedang dimatikan. Aktifkan GPS terlebih dahulu.',
        code: 'LOCATION_SERVICE_DISABLED',
      );
    }

    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      throw const ApiException(
        message:
            'Izin lokasi ditolak. Aktifkan izin lokasi untuk aplikasi ini.',
        code: 'LOCATION_PERMISSION_DENIED',
      );
    }

    if (permission == LocationPermission.deniedForever) {
      throw const ApiException(
        message:
            'Izin lokasi ditolak permanen. Buka pengaturan aplikasi untuk '
            'mengaktifkannya kembali.',
        code: 'LOCATION_PERMISSION_DENIED_FOREVER',
      );
    }

    try {
      final Position position = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: highAccuracy
              ? LocationAccuracy.best
              : LocationAccuracy.medium,
          timeLimit: AppConstants.gpsTimeout,
        ),
      );

      _cached = position;

      return PositionFix(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
      );
    } on TimeoutException {
      throw const ApiException(
        message:
            'Sinyal GPS tidak ditemukan. Pindahkan perangkat ke area terbuka '
            'lalu coba lagi.',
        code: 'GPS_TIMEOUT',
      );
    } on LocationServiceDisabledException {
      throw const ApiException(
        message: 'Layanan lokasi sedang dimatikan.',
        code: 'LOCATION_SERVICE_DISABLED',
      );
    } on PermissionDeniedException {
      throw const ApiException(
        message: 'Izin lokasi ditolak.',
        code: 'LOCATION_PERMISSION_DENIED',
      );
    }
  }

  /// Opens the system settings so a permanently denied permission can be fixed.
  Future<void> openSettings() => Geolocator.openAppSettings();

  Future<void> openLocationSettings() => Geolocator.openLocationSettings();

  void clearCache() {
    _cached = null;
  }
}
