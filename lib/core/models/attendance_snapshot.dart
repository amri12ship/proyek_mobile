import 'attendance_location.dart';
import 'attendance_record.dart';

/// Daily state returned by `GET /api/v1/attendance/today`.
class AttendanceSnapshot {
  const AttendanceSnapshot({
    required this.date,
    required this.status,
    required this.canCheckIn,
    required this.canCheckOut,
    required this.isWorkday,
    required this.isHoliday,
    required this.locations,
    required this.workStart,
    required this.workEnd,
    required this.toleranceMinutes,
    required this.ticketTtlSeconds,
    required this.requireSelfie,
    this.timezone,
    this.scheduleStart,
    this.scheduleEnd,
    this.record,
  });

  factory AttendanceSnapshot.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> workHours =
        json['work_hours'] is Map<String, dynamic>
        ? json['work_hours'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final Map<String, dynamic> validation =
        json['validation'] is Map<String, dynamic>
        ? json['validation'] as Map<String, dynamic>
        : const <String, dynamic>{};
    final Object? schedule = json['schedule'];
    final Object? record = json['record'];

    return AttendanceSnapshot(
      date: '${json['date'] ?? ''}',
      timezone: _text(json['timezone']),
      status: '${json['status'] ?? 'belum_absen'}',
      canCheckIn: json['can_check_in'] == true,
      canCheckOut: json['can_check_out'] == true,
      isWorkday: json['is_workday'] == true,
      isHoliday: json['is_holiday'] == true,
      workStart: '${workHours['start'] ?? '--:--'}',
      workEnd: '${workHours['end'] ?? '--:--'}',
      toleranceMinutes: _int(workHours['tolerance_minutes']),
      ticketTtlSeconds: _int(validation['ticket_ttl_seconds']),
      requireSelfie: validation['require_selfie'] == true,
      scheduleStart: schedule is Map<String, dynamic>
          ? _text(schedule['start'])
          : null,
      scheduleEnd: schedule is Map<String, dynamic>
          ? _text(schedule['end'])
          : null,
      locations: (json['locations'] as List<Object?>? ?? const <Object?>[])
          .whereType<Map<String, dynamic>>()
          .map(AttendanceLocation.fromJson)
          .toList(growable: false),
      record: record is Map<String, dynamic>
          ? AttendanceRecord.fromJson(record)
          : null,
    );
  }

  final String date;
  final String? timezone;
  final String status;
  final bool canCheckIn;
  final bool canCheckOut;
  final bool isWorkday;
  final bool isHoliday;
  final String workStart;
  final String workEnd;
  final int toleranceMinutes;
  final int ticketTtlSeconds;
  final bool requireSelfie;
  final String? scheduleStart;
  final String? scheduleEnd;
  final List<AttendanceLocation> locations;
  final AttendanceRecord? record;

  /// True when at least one location currently accepts the employee.
  bool get hasReachableLocation =>
      locations.isNotEmpty &&
      locations.any((AttendanceLocation l) => !l.isTooFar);

  /// The nearest location, used to guide the employee to the right point.
  AttendanceLocation? get nearestLocation {
    AttendanceLocation? nearest;
    double? best;

    for (final AttendanceLocation location in locations) {
      final double? distance = location.distance;

      if (distance == null) {
        continue;
      }

      if (best == null || distance < best) {
        best = distance;
        nearest = location;
      }
    }

    return nearest;
  }

  bool get hasCheckedIn => record != null && record!.checkIn != null;

  bool get hasCheckedOut => record != null && record!.checkOut != null;

  String get statusLabel {
    switch (status) {
      case 'sudah_masuk':
        return 'Sudah check-in';
      case 'selesai':
        return 'Absensi selesai';
      case 'izin':
        return 'Izin';
      case 'sakit':
        return 'Sakit';
      case 'cuti':
        return 'Cuti';
      case 'alpha':
        return 'Alpa';
      default:
        return 'Belum absen';
    }
  }
}

int _int(Object? value) {
  if (value is num) {
    return value.round();
  }

  return int.tryParse('$value') ?? 0;
}

String? _text(Object? value) {
  if (value == null) {
    return null;
  }

  final String text = '$value'.trim();

  return text.isEmpty ? null : text;
}
