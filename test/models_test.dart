import 'package:flutter_test/flutter_test.dart';
import 'package:proyek_mobile/core/models/app_user.dart';
import 'package:proyek_mobile/core/models/attendance_location.dart';
import 'package:proyek_mobile/core/models/attendance_record.dart';
import 'package:proyek_mobile/core/models/attendance_snapshot.dart';
import 'package:proyek_mobile/core/models/qr_ticket.dart';
import 'package:proyek_mobile/core/utils/formatters.dart';

void main() {
  group('AppUser', () {
    test('reads the nested employee profile returned by the API', () {
      final AppUser user = AppUser.fromJson(<String, dynamic>{
        'id': 2,
        'name': 'Budi Santoso',
        'email': 'budi@absensi.test',
        'role': 'employee',
        'phone': '0812',
        'employee': <String, dynamic>{
          'nik': 'EMP-002',
          'department': 'IT',
          'position': 'Staff',
        },
      });

      expect(user.id, 2);
      expect(user.name, 'Budi Santoso');
      expect(user.nik, 'EMP-002');
      expect(user.department, 'IT');
      expect(user.position, 'Staff');
      expect(user.phone, '0812');
      expect(user.isEmployee, isTrue);
      expect(user.initials, 'BS');
    });

    test('falls back gracefully when there is no employee profile', () {
      final AppUser user = AppUser.fromJson(<String, dynamic>{
        'id': 1,
        'name': 'Admin',
        'email': 'admin@absensi.test',
        'role': 'admin',
        'employee': null,
      });

      expect(user.nik, isNull);
      expect(user.department, isNull);
      expect(user.isEmployee, isFalse);
    });
  });

  group('AttendanceLocation', () {
    test('parses the distance and range flag computed by the server', () {
      final AttendanceLocation location = AttendanceLocation.fromJson(
        <String, dynamic>{
          'id': 1,
          'name': 'Kantor Pusat',
          'address': 'Jl. Merdeka',
          'latitude': -6.17539,
          'longitude': 106.82715,
          'radius': 250,
          'distance': 120.4,
          'within_range': true,
        },
      );

      expect(location.name, 'Kantor Pusat');
      expect(location.radius, 250);
      expect(location.distance, closeTo(120.4, 0.001));
      expect(location.isTooFar, isFalse);
    });

    test(
      'marks a location as too far when the device is outside the radius',
      () {
        final AttendanceLocation location = AttendanceLocation.fromJson(
          <String, dynamic>{
            'id': 2,
            'name': 'Gudang',
            'radius': 100,
            'distance': 900.0,
            'within_range': false,
          },
        );

        expect(location.isTooFar, isTrue);
      },
    );

    test('is not too far when the distance is unknown', () {
      final AttendanceLocation location = AttendanceLocation.fromJson(
        <String, dynamic>{'id': 3, 'name': 'Kantor', 'radius': 100},
      );

      expect(location.distance, isNull);
      expect(location.isTooFar, isFalse);
    });
  });

  group('AttendanceSnapshot', () {
    Map<String, dynamic> payload({bool checkedIn = false}) {
      return <String, dynamic>{
        'date': '2026-09-28',
        'timezone': 'Asia/Jakarta',
        'status': checkedIn ? 'sudah_masuk' : 'belum_absen',
        'can_check_in': !checkedIn,
        'can_check_out': checkedIn,
        'is_workday': true,
        'is_holiday': false,
        'schedule': <String, dynamic>{'start': '08:00', 'end': '17:00'},
        'work_hours': <String, dynamic>{
          'start': '08:00',
          'end': '17:00',
          'tolerance_minutes': 15,
        },
        'validation': <String, dynamic>{
          'ticket_ttl_seconds': 120,
          'require_selfie': true,
        },
        'record': checkedIn
            ? <String, dynamic>{
                'id': 9,
                'date': '2026-09-28',
                'status': 'hadir',
                'check_in': '2026-09-28T08:05:00+07:00',
              }
            : null,
        'locations': <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 1,
            'name': 'Kantor Pusat',
            'radius': 250,
            'distance': 80.0,
            'within_range': true,
          },
        ],
      };
    }

    test('parses the state before check-in', () {
      final AttendanceSnapshot snapshot = AttendanceSnapshot.fromJson(
        payload(),
      );

      expect(snapshot.date, '2026-09-28');
      expect(snapshot.status, 'belum_absen');
      expect(snapshot.canCheckIn, isTrue);
      expect(snapshot.canCheckOut, isFalse);
      expect(snapshot.hasCheckedIn, isFalse);
      expect(snapshot.requireSelfie, isTrue);
      expect(snapshot.ticketTtlSeconds, 120);
      expect(snapshot.toleranceMinutes, 15);
      expect(snapshot.workStart, '08:00');
      expect(snapshot.statusLabel, 'Belum absen');
    });

    test('parses the state after check-in and reports the record', () {
      final AttendanceSnapshot snapshot = AttendanceSnapshot.fromJson(
        payload(checkedIn: true),
      );

      expect(snapshot.canCheckIn, isFalse);
      expect(snapshot.canCheckOut, isTrue);
      expect(snapshot.hasCheckedIn, isTrue);
      expect(snapshot.hasCheckedOut, isFalse);
      expect(snapshot.record?.status, 'hadir');
      expect(snapshot.record?.checkIn, isNotNull);
      expect(snapshot.statusLabel, 'Sudah check-in');
    });

    test('picks the nearest location out of the assigned list', () {
      final AttendanceSnapshot snapshot = AttendanceSnapshot.fromJson(
        <String, dynamic>{
          ...payload(),
          'locations': <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 1,
              'name': 'Jauh',
              'radius': 100,
              'distance': 800.0,
              'within_range': false,
            },
            <String, dynamic>{
              'id': 2,
              'name': 'Dekat',
              'radius': 100,
              'distance': 20.0,
              'within_range': true,
            },
          ],
        },
      );

      expect(snapshot.nearestLocation?.name, 'Dekat');
      expect(snapshot.hasReachableLocation, isTrue);
    });

    test('reports no reachable location when every one is too far', () {
      final AttendanceSnapshot snapshot = AttendanceSnapshot.fromJson(
        <String, dynamic>{
          ...payload(),
          'locations': <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 1,
              'name': 'Jauh',
              'radius': 100,
              'distance': 800.0,
              'within_range': false,
            },
          ],
        },
      );

      expect(snapshot.hasReachableLocation, isFalse);
    });
  });

  group('AttendanceRecord', () {
    test('formats the work duration in hours and minutes', () {
      final AttendanceRecord record = AttendanceRecord.fromJson(
        <String, dynamic>{
          'id': 5,
          'date': '2026-09-28',
          'status': 'hadir',
          'check_in': '2026-09-28T08:00:00+07:00',
          'check_out': '2026-09-28T17:30:00+07:00',
          'work_minutes': 570,
          'location': <String, dynamic>{'id': 1, 'name': 'Kantor Pusat'},
          'has_selfie': true,
        },
      );

      expect(record.workLabel, '9j 30m');
      expect(record.locationName, 'Kantor Pusat');
      expect(record.hasSelfie, isTrue);
      expect(record.isLate, isFalse);
      expect(record.checkIn, isNotNull);
      expect(record.checkOut, isNotNull);
    });

    test('flags a late arrival', () {
      final AttendanceRecord record = AttendanceRecord.fromJson(
        <String, dynamic>{'id': 6, 'status': 'terlambat', 'work_minutes': 480},
      );

      expect(record.isLate, isTrue);
      expect(record.statusLabel, 'Terlambat');
      expect(record.workLabel, '8j');
    });

    test('shows a dash when there is no work duration yet', () {
      final AttendanceRecord record = AttendanceRecord.fromJson(
        <String, dynamic>{'id': 7, 'status': 'hadir'},
      );

      expect(record.workLabel, '-');
      expect(record.locationName, isNull);
    });
  });

  group('QrTicket', () {
    final DateTime issued = DateTime(2026, 9, 28, 8, 0, 0);

    QrTicket ticket() {
      return QrTicket.fromJson(<String, dynamic>{
        'ticket': 'abc123',
        'expires_at': issued
            .add(const Duration(seconds: 120))
            .toIso8601String(),
        'expires_in_seconds': 120,
        'location': <String, dynamic>{
          'id': 1,
          'name': 'Kantor Pusat',
          'radius': 250,
        },
      });
    }

    test('is valid until the expiry moment', () {
      final QrTicket parsed = ticket();

      expect(parsed.ticket, 'abc123');
      expect(parsed.locationName, 'Kantor Pusat');
      expect(parsed.locationRadius, 250);
      expect(parsed.isValidAt(issued.add(const Duration(seconds: 30))), isTrue);
      expect(parsed.remainingAt(issued), const Duration(seconds: 120));
    });

    test('expires once the moment is reached', () {
      final QrTicket parsed = ticket();

      expect(
        parsed.isValidAt(issued.add(const Duration(seconds: 121))),
        isFalse,
      );
      expect(
        parsed.remainingAt(issued.add(const Duration(seconds: 200))),
        Duration.zero,
      );
    });
  });

  group('Formatters', () {
    test('formats distances below and above one kilometre', () {
      expect(Formatters.distance(84.6), '85 m');
      expect(Formatters.distance(1500), '1.5 km');
      expect(Formatters.distance(null), '-');
    });

    test('formats the GPS accuracy', () {
      expect(Formatters.accuracy(8.4), '±8 m');
      expect(Formatters.accuracy(null), '-');
    });

    test('formats a countdown and never shows a negative value', () {
      expect(Formatters.countdown(const Duration(seconds: 125)), '02:05');
      expect(Formatters.countdown(const Duration(seconds: -5)), '00:00');
    });

    test('formats a null time as a placeholder', () {
      expect(Formatters.time(null), '--:--');
    });
  });
}
