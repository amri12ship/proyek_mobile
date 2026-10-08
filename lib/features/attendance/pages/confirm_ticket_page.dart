import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/models/attendance_record.dart';
import '../../../core/models/qr_ticket.dart';
import '../../../core/state/attendance_controller.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/gradient_button.dart';
import '../../../core/widgets/page_body.dart';
import 'scan_qr_page.dart';
import 'selfie_capture_page.dart';

/// Final step of the check-in flow: review the ticket, confirm the GPS fix,
/// take the selfie when the server requires it, then submit.
class ConfirmTicketPage extends StatefulWidget {
  const ConfirmTicketPage({super.key, required this.ticket});

  final QrTicket ticket;

  @override
  State<ConfirmTicketPage> createState() => _ConfirmTicketPageState();
}

class _ConfirmTicketPageState extends State<ConfirmTicketPage> {
  File? _selfie;

  Future<void> _takeSelfie() async {
    if (!widget.ticket.isValidAt(DateTime.now())) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Ticket QR sudah kedaluwarsa. Tekan "Scan ulang" untuk '
            'mendapat ticket baru.',
          ),
        ),
      );
      return;
    }

    final File? file = await Navigator.of(context).push<File>(
      MaterialPageRoute<File>(
        builder: (BuildContext context) =>
            const SelfieCapturePage(title: 'Selfie Check-In'),
      ),
    );

    if (file != null && mounted) {
      setState(() => _selfie = file);
    }
  }

  Future<void> _submit() async {
    final AttendanceController controller = context
        .read<AttendanceController>();
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final NavigatorState navigator = Navigator.of(context);

    String? selfiePath = _selfie?.path;

    if (selfiePath != null) {
      final File file = File(selfiePath);

      if (!file.existsSync()) {
        setState(() => _selfie = null);
        messenger.showSnackBar(
          const SnackBar(
            content: Text(
              'Foto selfie sudah tidak tersedia. Ambil ulang foto selfie.',
            ),
          ),
        );
        return;
      }

      final String? uploaded = await controller.uploadSelfie(file);

      if (uploaded == null) {
        return;
      }

      selfiePath = uploaded;
      _deleteSelfie();
    }

    final AttendanceRecord? record = await controller.checkIn(
      selfiePath: selfiePath,
    );

    if (record == null || !mounted) {
      return;
    }

    _selfie = null;

    await showDialog<void>(
      context: context,
      builder: (BuildContext context) {
        final ThemeData theme = Theme.of(context);
        final AppBrand brand = AppBrand.of(context);

        return AlertDialog(
          contentPadding: const EdgeInsets.fromLTRB(24, 28, 24, 8),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                height: 76,
                width: 76,
                decoration: BoxDecoration(
                  gradient: brand.successGradient,
                  shape: BoxShape.circle,
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: brand.success.first.withValues(alpha: 0.4),
                      blurRadius: 20,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.check_rounded,
                  size: 42,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Check-in berhasil',
                style: theme.textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Text(
                'Kehadiran Anda sudah tercatat.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 18),
              DetailRow(
                label: 'Waktu',
                value: Formatters.time(record.checkIn),
                divider: true,
              ),
              DetailRow(
                label: 'Status',
                value: record.statusLabel,
                divider: record.locationName != null,
              ),
              if (record.locationName != null)
                DetailRow(
                  label: 'Lokasi',
                  value: record.locationName!,
                  divider: true,
                ),
              DetailRow(
                label: 'Jarak',
                value: Formatters.distance(record.checkInDistance),
              ),
            ],
          ),
          actionsPadding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
          actions: <Widget>[
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Selesai'),
              ),
            ),
          ],
        );
      },
    );

    if (navigator.canPop()) {
      navigator.pop();
    }

    messenger.hideCurrentSnackBar();
  }

  void _deleteSelfie() {
    final File? file = _selfie;
    _selfie = null;

    if (file != null && file.existsSync()) {
      file.deleteSync();
    }
  }

  @override
  void dispose() {
    _deleteSelfie();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;
    final AttendanceController controller = context
        .watch<AttendanceController>();
    final bool requireSelfie = controller.snapshot?.requireSelfie ?? false;
    final bool busy = controller.isSubmitting;
    final bool needSelfie = requireSelfie && _selfie == null;

    return Scaffold(
      appBar: AppBar(title: const Text('Konfirmasi Absensi')),
      body: SafeArea(
        child: PageBody(
          child: ListView(
            children: <Widget>[
              TicketSummary(ticket: widget.ticket),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Lokasi saat ini',
                icon: Icons.my_location_rounded,
                trailing: IconButton(
                  tooltip: 'Perbarui GPS',
                  onPressed: controller.isLocating
                      ? null
                      : () => controller.refreshLocation(),
                  icon: controller.isLocating
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded),
                ),
                child: _LocationStatus(controller: controller),
              ),
              if (requireSelfie) ...<Widget>[
                const SizedBox(height: 16),
                SectionCard(
                  title: 'Selfie',
                  icon: Icons.face_rounded,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        _selfie == null
                            ? 'Server mewajibkan selfie sebagai bukti kehadiran.'
                            : 'Selfie siap diunggah.',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (_selfie == null)
                        GradientButton(
                          label: 'Ambil selfie',
                          icon: Icons.photo_camera_front_rounded,
                          isLoading: busy,
                          onPressed: busy ? null : _takeSelfie,
                        )
                      else
                        Row(
                          children: <Widget>[
                            ClipRRect(
                              borderRadius: BorderRadius.circular(
                                AppTheme.cornerRadiusSmall,
                              ),
                              child: Image.file(
                                File(_selfie!.path),
                                height: 96,
                                width: 96,
                                fit: BoxFit.cover,
                              ),
                            ),
                            const SizedBox(width: 12),
                            OutlinedButton.icon(
                              onPressed: busy ? null : _takeSelfie,
                              icon: const Icon(Icons.refresh_rounded),
                              label: const Text('Ulangi'),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              ],
              if (controller.error != null) ...<Widget>[
                const SizedBox(height: 16),
                ErrorBanner(
                  message: controller.error!,
                  onDismiss: controller.clearError,
                ),
              ],
              const SizedBox(height: 24),
              GradientButton.success(
                label: busy
                    ? 'Mengirim...'
                    : (needSelfie ? 'Ambil selfie dulu' : 'Check-in sekarang'),
                icon: needSelfie
                    ? Icons.photo_camera_front_rounded
                    : Icons.login_rounded,
                isLoading: busy,
                onPressed: busy
                    ? null
                    : (needSelfie ? _takeSelfie : _submit),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: busy
                    ? null
                    : () => Navigator.of(context).pushReplacement(
                        MaterialPageRoute<void>(
                          builder: (BuildContext context) => const ScanQrPage(),
                        ),
                      ),
                child: const Text('Scan ulang'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LocationStatus extends StatelessWidget {
  const _LocationStatus({required this.controller});

  final AttendanceController controller;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    if (controller.fix == null) {
      return Text(
        'Sinyal GPS belum tersedia. Tekan tombol segarkan untuk mencoba lagi.',
        style: theme.textTheme.bodyMedium?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      );
    }

    final double? distance = controller.snapshot?.nearestLocation?.distance;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        DetailRow(
          label: 'Koordinat',
          value:
              '${controller.fix!.latitude.toStringAsFixed(5)}, '
              '${controller.fix!.longitude.toStringAsFixed(5)}',
        ),
        DetailRow(
          label: 'Akurasi',
          value: Formatters.accuracy(controller.fix!.accuracy),
        ),
        if (distance != null)
          DetailRow(
            label: 'Jarak terdekat',
            value: Formatters.distance(distance),
          ),
      ],
    );
  }
}
