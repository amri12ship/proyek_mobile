import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

import '../models/attendance_location.dart';
import '../models/attendance_record.dart';
import '../models/attendance_snapshot.dart';
import '../models/qr_ticket.dart';
import '../network/api_exception.dart';
import '../services/attendance_repository.dart';
import '../services/location_service.dart';

/// Owns the attendance screens: today's snapshot, the live GPS fix, the QR
/// ticket in flight, and the history list.
class AttendanceController extends ChangeNotifier {
  AttendanceController({
    required this._repository,
    required this._locationService,
  });

  final AttendanceRepository _repository;
  final LocationService _locationService;

  AttendanceSnapshot? _snapshot;
  AttendanceHistory? _history;
  QrTicket? _ticket;
  PositionFix? _fix;

  bool _loading = false;
  bool _locating = false;
  bool _submitting = false;
  String? _error;

  AttendanceSnapshot? get snapshot => _snapshot;

  AttendanceHistory? get history => _history;

  QrTicket? get ticket => _ticket;

  PositionFix? get fix => _fix;

  bool get isLoading => _loading;

  bool get isLocating => _locating;

  /// True while a check-in or check-out is being sent.
  bool get isSubmitting => _submitting;

  String? get error => _error;

  /// The locations with the live distance filled in, if a fix is available.
  List<AttendanceLocation> get locations =>
      _snapshot?.locations ?? const <AttendanceLocation>[];

  /// Reloads today's state, using the cached GPS fix when there is one.
  Future<void> loadToday({bool forceGps = false}) async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      if (forceGps || _fix == null) {
        await _refreshFix(silent: true);
      }

      _snapshot = await _repository.today(
        latitude: _fix?.latitude,
        longitude: _fix?.longitude,
      );
    } on ApiException catch (error) {
      _error = error.message;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Gets a fresh, high accuracy position and reloads the distances.
  Future<bool> refreshLocation() async {
    _locating = true;
    _error = null;
    notifyListeners();

    try {
      final bool fixed = await _refreshFix();

      if (fixed) {
        _snapshot = await _repository.today(
          latitude: _fix?.latitude,
          longitude: _fix?.longitude,
        );
      }

      return fixed;
    } on ApiException catch (error) {
      _error = error.message;
      return false;
    } finally {
      _locating = false;
      notifyListeners();
    }
  }

  /// Exchanges a scanned QR payload for a single-use ticket.
  Future<QrTicket?> scanQr(String rawCode) async {
    _error = null;
    _submitting = true;
    notifyListeners();

    try {
      final QrTicket issued = await _repository.validateQr(
        code: rawCode,
        latitude: _fix?.latitude,
        longitude: _fix?.longitude,
      );

      _ticket = issued;

      return issued;
    } on ApiException catch (error) {
      _error = error.message;
      return null;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }

  /// Sends a check-in using the scanned ticket, a fresh GPS fix, and an
  /// optional selfie path returned by the upload endpoint.
  Future<AttendanceRecord?> checkIn({String? selfiePath}) {
    return _submit((PositionFix fix) {
      return _repository.checkIn(
        ticket: _requireTicket(),
        latitude: fix.latitude,
        longitude: fix.longitude,
        accuracy: fix.accuracy,
        selfie: selfiePath,
      );
    });
  }

  /// Sends a check-out. A fresh fix is required because the employee must
  /// still be inside the location radius when leaving.
  Future<AttendanceRecord?> checkOut({String? selfiePath}) {
    return _submit((PositionFix fix) {
      return _repository.checkOut(
        latitude: fix.latitude,
        longitude: fix.longitude,
        accuracy: fix.accuracy,
        selfie: selfiePath,
      );
    });
  }

  /// Uploads a captured selfie and returns the stored path for check-in/out.
  Future<String?> uploadSelfie(File file) async {
    _error = null;
    _submitting = true;
    notifyListeners();

    try {
      if (!file.existsSync()) {
        throw const ApiException(
          message:
              'Foto selfie sudah tidak tersedia. Ambil ulang foto selfie '
              'sebelum melanjutkan.',
          code: 'FILE_UNAVAILABLE',
        );
      }

      return await _repository.uploadSelfie(file);
    } on ApiException catch (error) {
      _error = error.message;
      return null;
    } on FileSystemException catch (error) {
      _error =
          'Foto selfie tidak dapat dibaca. Ambil ulang foto selfie.';
      debugPrint('uploadSelfie: $error');
      return null;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }

  /// Loads the history list for a date range, defaulting to the last 30 days.
  Future<void> loadHistory({DateTimeRangeLike? range}) async {
    _loading = true;
    _error = null;
    notifyListeners();

    final DateFormat dayFormat = DateFormat('yyyy-MM-dd');
    final DateTime to = range?.to ?? DateTime.now();
    final DateTime from = range?.from ?? to.subtract(const Duration(days: 29));

    try {
      _history = await _repository.history(
        from: dayFormat.format(from),
        to: dayFormat.format(to),
      );
    } on ApiException catch (error) {
      _error = error.message;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  void clearError() {
    if (_error == null) {
      return;
    }

    _error = null;
    notifyListeners();
  }

  /// Drops the ticket so a stale QR payload cannot be reused after a failure.
  void clearTicket() {
    if (_ticket == null) {
      return;
    }

    _ticket = null;
    notifyListeners();
  }

  /// Called when the app returns to the foreground or a page is re-entered.
  void reset() {
    _snapshot = null;
    _history = null;
    _ticket = null;
    _error = null;
    _locationService.clearCache();
    _fix = null;
    notifyListeners();
  }

  Future<AttendanceRecord?> _submit(
    Future<AttendanceRecord> Function(PositionFix fix) request,
  ) async {
    _error = null;
    _submitting = true;
    notifyListeners();

    try {
      final PositionFix fix = await _requireFix();
      _fix = fix;

      final AttendanceRecord record = await request(fix);

      _ticket = null;

      try {
        _snapshot = await _repository.today(
          latitude: fix.latitude,
          longitude: fix.longitude,
        );
      } on ApiException {
        // The record is already stored server side and the ticket has been
        // consumed. Failing to refresh the dashboard must not turn a successful
        // submission into a failure, because retrying would then be rejected as
        // a reused ticket.
      }

      return record;
    } on ApiException catch (error) {
      _error = error.message;
      return null;
    } on FileSystemException catch (error) {
      _error = 'Berkas pada perangkat tidak dapat dibaca. Coba ulangi.';
      debugPrint('_submit: $error');
      return null;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }

  Future<bool> _refreshFix({bool silent = false}) async {
    try {
      _fix = await _locationService.currentFix();
      return true;
    } on ApiException catch (error) {
      _fix = _locationService.hasCachedFix ? _fix : null;

      if (!silent) {
        _error = error.message;
      }

      return false;
    }
  }

  Future<PositionFix> _requireFix() async {
    if (_fix != null) {
      return _fix!;
    }

    final PositionFix fix = await _locationService.currentFix();
    _fix = fix;

    return fix;
  }

  String _requireTicket() {
    final QrTicket? current = _ticket;

    if (current == null) {
      throw const ApiException(
        message: 'Ticket QR tidak ditemukan. Scan ulang kode QR lokasi.',
        code: 'MISSING_TICKET',
      );
    }

    if (!current.isValidAt(DateTime.now())) {
      _ticket = null;

      throw const ApiException(
        message: 'Ticket QR sudah kedaluwarsa. Scan ulang kode QR lokasi.',
        code: 'TICKET_EXPIRED',
      );
    }

    return current.ticket;
  }
}

/// A plain date range, kept independent of Flutter's material widgets.
class DateTimeRangeLike {
  const DateTimeRangeLike({required this.from, required this.to});

  final DateTime from;
  final DateTime to;
}
