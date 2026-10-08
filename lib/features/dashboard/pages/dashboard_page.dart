import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/models/attendance_location.dart';
import '../../../core/models/attendance_record.dart';
import '../../../core/models/attendance_snapshot.dart';
import '../../../core/state/attendance_controller.dart';
import '../../../core/state/session_controller.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/gradient_button.dart';
import '../../../core/widgets/lux_widgets.dart';
import '../../../core/widgets/page_body.dart';
import '../../../core/widgets/status_badge.dart';
import '../../attendance/pages/scan_qr_page.dart';
import '../../attendance/pages/selfie_capture_page.dart';

/// Main page of a signed-in employee: today's state, the reachable locations,
/// and the check-in / check-out actions.
class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<AttendanceController>().loadToday();
      }
    });
  }

  Future<void> _scan() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (BuildContext context) => const ScanQrPage(),
      ),
    );

    if (mounted) {
      await context.read<AttendanceController>().loadToday();
    }
  }

  Future<void> _checkOut() async {
    final AttendanceController controller = context
        .read<AttendanceController>();
    final bool requireSelfie = controller.snapshot?.requireSelfie ?? false;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    String? selfiePath;

    if (requireSelfie) {
      final File? file = await Navigator.of(context).push<File>(
        MaterialPageRoute<File>(
          builder: (BuildContext context) =>
              const SelfieCapturePage(title: 'Selfie Check-Out'),
        ),
      );

      if (file == null) {
        return;
      }

      final String? uploaded = await controller.uploadSelfie(file);

      if (uploaded == null) {
        return;
      }

      selfiePath = uploaded;

      if (file.existsSync()) {
        file.deleteSync();
      }
    }

    final AttendanceRecord? record = await controller.checkOut(
      selfiePath: selfiePath,
    );

    if (record == null || !mounted) {
      return;
    }

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          'Check-out dicatat pukul ${Formatters.time(record.checkOut)}'
          '${record.workMinutes == null ? '' : ' (${record.workLabel})'}.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final AttendanceController controller = context
        .watch<AttendanceController>();
    final AttendanceSnapshot? snapshot = controller.snapshot;

    if (controller.isLoading && snapshot == null) {
      return const _DashboardSkeleton();
    }

    if (snapshot == null) {
      return ErrorView(
        message: controller.error ?? 'Data kehadiran tidak dapat dimuat.',
        onRetry: () => controller.loadToday(forceGps: true),
      );
    }

    return RefreshIndicator(
      onRefresh: () => controller.loadToday(forceGps: true),
      child: PageBody(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: <Widget>[
            const _GreetingCard(),
            if (controller.error != null) ...<Widget>[
              const SizedBox(height: 16),
              ErrorBanner(
                message: controller.error!,
                onDismiss: controller.clearError,
              ),
            ],
            const SizedBox(height: 20),
            _ActionCard(
              snapshot: snapshot,
              onScan: controller.isSubmitting ? null : _scan,
              onCheckOut: controller.isSubmitting ? null : _checkOut,
              onRetry: controller.isSubmitting
                  ? null
                  : () => controller.loadToday(forceGps: true),
            ),
            const SizedBox(height: 16),
            _TodayCard(snapshot: snapshot),
            const SizedBox(height: 16),
            _LocationsCard(
              locations: snapshot.locations,
              isLocating: controller.isLocating,
              onRefresh: () => controller.refreshLocation(),
            ),
            const SizedBox(height: 16),
            _ValidationCard(snapshot: snapshot),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

/// Placeholder shown while the first payload is loading, so the page does not
/// flash an empty screen before the content arrives.
class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return PageBody(
      child: ListView(
        children: <Widget>[
          HeroCard(
            child: Row(
              children: <Widget>[
                Container(
                  height: 56,
                  width: 56,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.08),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Container(
                        height: 16,
                        width: 160,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.10),
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      const SizedBox(height: 10),
                      Container(
                        height: 12,
                        width: 110,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.07),
                          borderRadius: BorderRadius.circular(6),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const SurfaceCard(
            child: SizedBox(
              height: 54,
              child: Center(child: CircularProgressIndicator(strokeWidth: 2.4)),
            ),
          ),
          const SizedBox(height: 16),
          const SurfaceCard(
            child: SizedBox(
              height: 120,
              child: Center(
                child: Icon(
                  Icons.hourglass_empty_rounded,
                  size: 28,
                  color: Colors.black26,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Live wall-clock badge in the greeting hero.
///
/// Ticks every second, but the seconds are deliberately dimmed and smaller than
/// the hour and minute so the eye settles on the time instead of chasing a
/// number that keeps moving.
class _ClockBadge extends StatefulWidget {
  const _ClockBadge();

  @override
  State<_ClockBadge> createState() => _ClockBadgeState();
}

class _ClockBadgeState extends State<_ClockBadge> {
  late DateTime _now = DateTime.now();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() => _now = DateTime.now());
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  static String _two(int value) => value.toString().padLeft(2, '0');

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: brand.gold.withValues(alpha: 0.28)),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            Colors.white.withValues(alpha: 0.10),
            Colors.white.withValues(alpha: 0.02),
          ],
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: <Widget>[
              GoldText(
                '${_two(_now.hour)}:${_two(_now.minute)}',
                style: theme.textTheme.titleLarge?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                  fontFeatures: const <FontFeature>[
                    FontFeature.tabularFigures(),
                  ],
                ),
              ),
              Text(
                ':${_two(_now.second)}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.55),
                  fontWeight: FontWeight.w600,
                  fontFeatures: const <FontFeature>[
                    FontFeature.tabularFigures(),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          const SizedBox(width: 46, child: GoldRule()),
          const SizedBox(height: 5),
          const Eyebrow('WIB'),
        ],
      ),
    );
  }
}

class _GreetingCard extends StatelessWidget {
  const _GreetingCard();

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final SessionController session = context.watch<SessionController>();
    final String name = session.user?.name ?? 'Karyawan';
    final String firstName = name.split(' ').first;

    return HeroCard(
      child: Row(
        children: <Widget>[
          Container(
            height: 56,
            width: 56,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: AppBrand.of(context).gold.withValues(alpha: 0.45),
                width: 1.2,
              ),
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  AppBrand.of(context).gold.withValues(alpha: 0.28),
                  AppBrand.of(context).gold.withValues(alpha: 0.06),
                ],
              ),
            ),
            child: Text(
              session.user?.initials ?? '?',
              style: theme.textTheme.titleMedium?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Halo, $firstName',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  session.user?.department ?? session.user?.email ?? '-',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.7),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          const _ClockBadge(),
        ],
      ),
    );
  }
}

class _TodayCard extends StatelessWidget {
  const _TodayCard({required this.snapshot});

  final AttendanceSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AttendanceRecord? record = snapshot.record;

    return SectionCard(
      title: Formatters.longDate(DateTime.now()),
      subtitle: 'Jam kerja ${snapshot.workStart} - ${snapshot.workEnd}',
      icon: Icons.today_rounded,
      trailing: StatusBadge(
        label: snapshot.statusLabel,
        tone: _toneFor(snapshot),
        icon: _iconFor(snapshot),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (snapshot.isHoliday || !snapshot.isWorkday)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Align(
                alignment: Alignment.centerLeft,
                child: snapshot.isHoliday
                    ? const StatusBadge.warning(
                        label: 'Hari libur nasional',
                        icon: Icons.celebration_rounded,
                      )
                    : const StatusBadge.neutral(
                        label: 'Bukan hari kerja',
                        icon: Icons.weekend_rounded,
                      ),
              ),
            ),
          DetailRow(
            label: 'Check-in',
            value: Formatters.time(record?.checkIn),
            divider: true,
          ),
          DetailRow(
            label: 'Check-out',
            value: Formatters.time(record?.checkOut),
            divider: record?.locationName != null,
          ),
          DetailRow(
            label: 'Durasi',
            value: record?.workLabel ?? '-',
            divider: record?.locationName != null,
          ),
          if (record?.locationName != null)
            DetailRow(label: 'Lokasi', value: record!.locationName!),
          if (record != null) ...<Widget>[
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'Selisih check-in ${Formatters.distance(record.checkInDistance)} '
                    'dari titik lokasi',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
                if (record.isLate)
                  const StatusBadge.warning(
                    label: 'Terlambat',
                    icon: Icons.running_with_errors,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  static BadgeTone _toneFor(AttendanceSnapshot snapshot) {
    if (snapshot.status == 'belum_absen') {
      return BadgeTone.neutral;
    }

    return snapshot.status == 'alpha' ? BadgeTone.danger : BadgeTone.success;
  }

  static IconData _iconFor(AttendanceSnapshot snapshot) {
    switch (snapshot.status) {
      case 'sudah_masuk':
        return Icons.login_rounded;
      case 'selesai':
        return Icons.task_alt_rounded;
      case 'alpha':
        return Icons.error_rounded;
      default:
        return Icons.schedule_rounded;
    }
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.snapshot,
    required this.onScan,
    required this.onCheckOut,
    required this.onRetry,
  });

  final AttendanceSnapshot snapshot;
  final VoidCallback? onScan;
  final VoidCallback? onCheckOut;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);
    final bool blocked = !snapshot.canCheckIn && !snapshot.canCheckOut;

    return SurfaceCard(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Eyebrow(blocked ? 'Tidak tersedia' : 'Langkah berikutnya'),
          const SizedBox(height: 10),
          Text(
            _headline(snapshot),
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(
            _subtitle(snapshot),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 18),
          if (snapshot.canCheckIn)
            GradientButton.success(
              label: 'Scan QR & Check-in',
              icon: Icons.qr_code_scanner_rounded,
              onPressed: onScan,
            )
          else if (snapshot.canCheckOut)
            GradientButton.warning(
              label: 'Check-out',
              icon: Icons.logout_rounded,
              onPressed: onCheckOut,
            )
          else
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(_idleLabel(snapshot)),
            ),
          const SizedBox(height: 16),
          const GoldRule(),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(
                snapshot.canCheckOut
                    ? Icons.verified_rounded
                    : Icons.info_outline_rounded,
                size: 16,
                color: snapshot.canCheckOut
                    ? brand.gold
                    : theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _footnote(snapshot),
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  static String _headline(AttendanceSnapshot snapshot) {
    if (snapshot.canCheckIn) {
      return 'Catat kehadiran Anda';
    }

    if (snapshot.canCheckOut) {
      return 'Selesaikan jam kerja';
    }

    return 'Absensi hari ini selesai';
  }

  static String _subtitle(AttendanceSnapshot snapshot) {
    if (snapshot.canCheckIn) {
      return 'Pindai QR lokasi yang sedang berlaku untuk check-in.';
    }

    if (snapshot.canCheckOut) {
      return 'Pastikan selfie diambil sebelum menutup kehadiran.';
    }

    return 'Tidak ada aksi absensi yang bisa dilakukan saat ini.';
  }

  static String _footnote(AttendanceSnapshot snapshot) {
    if (snapshot.canCheckOut) {
      return 'Check-out juga memerlukan selfie dan GPS di dalam radius lokasi.';
    }

    if (snapshot.canCheckIn) {
      return 'Pastikan perangkat berada di dalam radius lokasi yang dipindai.';
    }

    return 'Periksa kembali lokasi terdaftar dan jadwal kerja hari ini.';
  }

  static String _idleLabel(AttendanceSnapshot snapshot) {
    if (snapshot.hasCheckedOut) {
      return 'Absensi hari ini selesai';
    }

    if (snapshot.hasCheckedIn) {
      return 'Absensi sedang berjalan';
    }

    if (!snapshot.isWorkday) {
      return 'Hari ini bukan hari kerja';
    }

    if (snapshot.isHoliday) {
      return 'Hari ini libur nasional';
    }

    return 'Belum bisa absen';
  }
}

class _LocationsCard extends StatelessWidget {
  const _LocationsCard({
    required this.locations,
    required this.isLocating,
    required this.onRefresh,
  });

  final List<AttendanceLocation> locations;
  final bool isLocating;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Lokasi terdaftar',
      icon: Icons.place_rounded,
      trailing: IconButton(
        tooltip: 'Segarkan lokasi',
        onPressed: isLocating ? null : onRefresh,
        icon: isLocating
            ? const SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.my_location_rounded),
      ),
      child: locations.isEmpty
          ? const Text('Belum ada lokasi yang terdaftar.')
          : Column(
              children: locations
                  .map(
                    (AttendanceLocation location) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.surfaceContainer,
                        borderRadius: BorderRadius.circular(
                          AppTheme.cornerRadiusSmall,
                        ),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          IconTile(
                            icon: location.isTooFar
                                ? Icons.location_off_rounded
                                : Icons.location_on_rounded,
                            color: location.isTooFar
                                ? Theme.of(context).colorScheme.error
                                : null,
                            size: 38,
                            radius: 10,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  location.name,
                                  style: Theme.of(context).textTheme.bodyMedium
                                      ?.copyWith(
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                                if (location.address != null) ...<Widget>[
                                  const SizedBox(height: 2),
                                  Text(
                                    location.address!,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: Theme.of(
                                            context,
                                          ).colorScheme.onSurfaceVariant,
                                        ),
                                  ),
                                ],
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 6,
                                  crossAxisAlignment: WrapCrossAlignment.center,
                                  children: <Widget>[
                                    Text(
                                      Formatters.distance(location.distance),
                                      style: Theme.of(context).textTheme.bodySmall
                                          ?.copyWith(
                                            fontWeight: FontWeight.w600,
                                          ),
                                    ),
                                    if (location.distance != null)
                                      StatusBadge(
                                        label: location.isTooFar
                                            ? 'Di luar radius'
                                            : 'Dalam radius',
                                        tone: location.isTooFar
                                            ? BadgeTone.danger
                                            : BadgeTone.success,
                                      ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                  .toList(),
            ),
    );
  }
}

class _ValidationCard extends StatelessWidget {
  const _ValidationCard({required this.snapshot});

  final AttendanceSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final String? scheduleStart = snapshot.scheduleStart;

    return SectionCard(
      title: 'Aturan absensi',
      icon: Icons.rule_rounded,
      child: Column(
        children: <Widget>[
          DetailRow(
            label: 'Batas check-in',
            value: '${snapshot.workStart} (+${snapshot.toleranceMinutes} mnt)',
            divider: true,
          ),
          DetailRow(
            label: 'Batas check-out',
            value: snapshot.workEnd,
            divider: scheduleStart != null,
          ),
          if (scheduleStart != null)
            DetailRow(
              label: 'Jadwal hari ini',
              value: scheduleStart,
              divider: true,
            ),
          DetailRow(
            label: 'Masa berlaku QR',
            value: '${snapshot.ticketTtlSeconds} detik',
            divider: snapshot.requireSelfie,
          ),
          DetailRow(
            label: 'Selfie',
            value: snapshot.requireSelfie ? 'Wajib' : 'Tidak wajib',
          ),
          if (snapshot.timezone != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Zona waktu server: ${snapshot.timezone}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
