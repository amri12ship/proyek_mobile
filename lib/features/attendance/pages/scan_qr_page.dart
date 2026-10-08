import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/models/qr_ticket.dart';
import '../../../core/state/attendance_controller.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/status_badge.dart';
import 'confirm_ticket_page.dart';

/// Camera screen that turns the location QR code into a single-use ticket.
///
/// The raw payload never leaves this screen: only the resulting ticket is
/// handed to the next page, so the check-in call never re-reads the code.
class ScanQrPage extends StatefulWidget {
  const ScanQrPage({super.key});

  @override
  State<ScanQrPage> createState() => _ScanQrPageState();
}

class _ScanQrPageState extends State<ScanQrPage> {
  final MobileScannerController _scanner = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const <BarcodeFormat>[BarcodeFormat.qrCode],
  );
  bool _handling = false;
  bool _torchOn = false;
  String? _error;

  @override
  void dispose() {
    _scanner.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handling) {
      return;
    }

    final String raw = capture.barcodes
        .map((Barcode barcode) => barcode.rawValue ?? '')
        .firstWhere((String value) => value.isNotEmpty, orElse: () => '');

    if (raw.isEmpty) {
      return;
    }

    setState(() {
      _handling = true;
      _error = null;
    });

    final AttendanceController controller = context.read<AttendanceController>();
    final QrTicket? ticket = await controller.scanQr(raw);

    if (!mounted) {
      return;
    }

    if (ticket == null) {
      setState(() {
        _handling = false;
        _error = controller.error ??
            'Kode QR tidak dapat dibaca. Pastikan Anda terdaftar pada lokasi ini.';
      });
      return;
    }

    await Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (BuildContext context) => ConfirmTicketPage(ticket: ticket),
      ),
    );

    if (mounted) {
      setState(() {
        _handling = false;
        _error = null;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan QR Lokasi'),
        actions: <Widget>[
          IconButton(
            tooltip: _torchOn ? 'Matikan senter' : 'Nyalakan senter',
            onPressed: () {
              setState(() => _torchOn = !_torchOn);
              _scanner.toggleTorch();
            },
            icon: Icon(
              _torchOn ? Icons.flash_on_rounded : Icons.flash_off_rounded,
            ),
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          MobileScanner(
            controller: _scanner,
            onDetect: _onDetect,
            errorBuilder: (BuildContext context, MobileScannerException error) {
              return ColoredBox(
                color: colors.surface,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Kamera tidak dapat dibuka: ${error.errorDetails?.message ?? error.errorCode.name}',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
              );
            },
          ),
          const _ScannerOverlay(),
          if (_handling)
            ColoredBox(
              color: colors.surface.withValues(alpha: 0.6),
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    CircularProgressIndicator(),
                    SizedBox(height: 16),
                    Text('Memvalidasi kode QR...'),
                  ],
                ),
              ),
            ),
          if (_error != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: ErrorBanner(
                message: _error!,
                onDismiss: () => setState(() => _error = null),
              ),
            ),
        ],
      ),
    );
  }
}

class _ScannerOverlay extends StatelessWidget {
  const _ScannerOverlay();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Center(
        child: Container(
          width: 240,
          height: 240,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: Theme.of(context).colorScheme.primary,
              width: 3,
            ),
          ),
          alignment: Alignment.bottomCenter,
          child: Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              'Arahkan kamera ke QR lokasi',
              style: Theme.of(context).textTheme.labelMedium
                  ?.copyWith(color: Theme.of(context).colorScheme.onSurface),
            ),
          ),
        ),
      ),
    );
  }
}

/// Shows the issued ticket with a live countdown before check-in is attempted.
class TicketSummary extends StatelessWidget {
  const TicketSummary({super.key, required this.ticket});

  final QrTicket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: brand.successGradient,
        borderRadius: BorderRadius.circular(AppTheme.cornerRadius),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: brand.success.first.withValues(alpha: 0.32),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                height: 42,
                width: 42,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.22),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.confirmation_number_rounded,
                  color: Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Ticket valid',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: Colors.white,
                      ),
                    ),
                    Text(
                      'Sekali pakai, berlaku singkat.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.85),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _TicketRow(label: 'Lokasi', value: ticket.locationName),
          _TicketRow(label: 'Radius', value: '${ticket.locationRadius} m'),
          const SizedBox(height: 14),
          TicketCountdown(ticket: ticket, onGradient: true),
        ],
      ),
    );
  }
}

/// Label and value pair rendered for the light-on-gradient ticket header.
class _TicketRow extends StatelessWidget {
  const _TicketRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            flex: 3,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ),
          Expanded(
            flex: 5,
            child: Text(
              value,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }
}

/// Live countdown of the ticket TTL. Turns into a warning when the remaining
/// time is under a minute, and into an expired badge when it hits zero.
class TicketCountdown extends StatefulWidget {
  const TicketCountdown({super.key, required this.ticket, this.onGradient = false});

  final QrTicket ticket;

  /// Renders the countdown in light text when it sits on a coloured gradient.
  final bool onGradient;

  @override
  State<TicketCountdown> createState() => _TicketCountdownState();
}

class _TicketCountdownState extends State<TicketCountdown> {
  late Duration _remaining = widget.ticket.remainingAt(DateTime.now());

  @override
  void initState() {
    super.initState();
    _remaining = widget.ticket.remainingAt(DateTime.now());
    _start();
  }

  void _start() {
    Future<void>.delayed(const Duration(milliseconds: 500), () {
      if (!mounted) {
        return;
      }

      setState(() {
        _remaining = widget.ticket.remainingAt(DateTime.now());
      });

      if (_remaining > Duration.zero) {
        _start();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool expired = _remaining <= Duration.zero;
    final bool almost = !expired && _remaining.inSeconds <= 60;
    final bool onGradient = widget.onGradient;

    if (expired) {
      return StatusBadge.danger(
        label: 'Ticket kedaluwarsa. Scan ulang.',
        icon: Icons.timer_off_rounded,
      );
    }

    final Color foreground = onGradient
        ? Colors.white
        : (almost ? theme.colorScheme.error : theme.colorScheme.onSurface);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: onGradient
            ? Colors.white.withValues(alpha: 0.18)
            : theme.colorScheme.surfaceContainer,
        borderRadius: BorderRadius.circular(AppTheme.cornerRadiusSmall),
      ),
      child: Row(
        children: <Widget>[
          Icon(
            almost ? Icons.timer_rounded : Icons.timer_outlined,
            size: 18,
            color: onGradient
                ? Colors.white
                : (almost ? theme.colorScheme.error : theme.colorScheme.primary),
          ),
          const SizedBox(width: 8),
          Text(
            'Berlaku ${Formatters.countdown(_remaining)} lagi',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
