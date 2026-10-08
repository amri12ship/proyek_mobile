/// A single attendance row, used by the dashboard and the history page.
class AttendanceRecord {
  const AttendanceRecord({
    required this.id,
    required this.date,
    required this.status,
    this.checkIn,
    this.checkOut,
    this.workMinutes,
    this.locationName,
    this.checkInDistance,
    this.checkOutDistance,
    this.hasSelfie = false,
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      id: _int(json['id']),
      date: '${json['date'] ?? json['attendance_date'] ?? ''}',
      status: '${json['status'] ?? 'hadir'}',
      checkIn: _dateTime(json['check_in']),
      checkOut: _dateTime(json['check_out']),
      workMinutes: json['work_minutes'] == null
          ? null
          : _int(json['work_minutes']),
      locationName: json['location'] is Map<String, dynamic>
          ? '${(json['location'] as Map<String, dynamic>)['name'] ?? '-'}'
          : _text(json['location_name']),
      checkInDistance: json['check_in_distance'] == null
          ? null
          : _double(json['check_in_distance']),
      checkOutDistance: json['check_out_distance'] == null
          ? null
          : _double(json['check_out_distance']),
      hasSelfie: json['has_selfie'] == true,
    );
  }

  final int id;
  final String date;
  final String status;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final int? workMinutes;
  final String? locationName;
  final double? checkInDistance;
  final double? checkOutDistance;
  final bool hasSelfie;

  bool get isLate => status == 'terlambat';

  String get statusLabel {
    switch (status) {
      case 'terlambat':
        return 'Terlambat';
      case 'izin':
        return 'Izin';
      case 'sakit':
        return 'Sakit';
      case 'cuti':
        return 'Cuti';
      case 'alpha':
        return 'Alpa';
      default:
        return 'Hadir';
    }
  }

  String get workLabel {
    final int? minutes = workMinutes;

    if (minutes == null || minutes == 0) {
      return '-';
    }

    final int hours = minutes ~/ 60;
    final int rest = minutes % 60;

    if (hours == 0) {
      return '${rest}m';
    }

    return rest == 0 ? '${hours}j' : '${hours}j ${rest}m';
  }
}

DateTime? _dateTime(Object? value) {
  if (value == null) {
    return null;
  }

  return DateTime.tryParse('$value')?.toLocal();
}

int _int(Object? value) {
  if (value is num) {
    return value.round();
  }

  return int.tryParse('$value') ?? 0;
}

double _double(Object? value) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse('$value') ?? 0;
}

String? _text(Object? value) {
  if (value == null) {
    return null;
  }

  final String text = '$value'.trim();

  return text.isEmpty ? null : text;
}
