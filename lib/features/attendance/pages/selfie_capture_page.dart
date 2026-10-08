import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/gradient_button.dart';

/// Front-camera capture used to satisfy the server side selfie requirement.
///
/// The page returns the captured [File] so the caller can upload it through
/// `POST /api/v1/attendance/selfie` and send the stored path with the
/// check-in or check-out payload.
class SelfieCapturePage extends StatefulWidget {
  const SelfieCapturePage({super.key, this.title = 'Ambil Selfie'});

  final String title;

  @override
  State<SelfieCapturePage> createState() => _SelfieCapturePageState();
}

class _SelfieCapturePageState extends State<SelfieCapturePage> {
  final ImagePicker _picker = ImagePicker();

  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _discardPending();
    super.dispose();
  }

  File? _pending;

  void _discardPending() {
    final File? file = _pending;
    _pending = null;

    if (file != null && file.existsSync()) {
      file.deleteSync();
    }
  }

  /// Copies the picked image out of the picker's cache directory.
  ///
  /// `image_picker` hands back a path inside a cache folder the platform is
  /// free to reclaim at any moment, and the returned file has to survive the
  /// camera page being disposed right after the pop. A private copy in the
  /// system temp directory is therefore taken before the file is passed on.
  Future<File> _stash(File source) async {
    final Directory dir = Directory(
      '${Directory.systemTemp.path}${Platform.pathSeparator}absensi_selfie',
    );

    if (!await dir.exists()) {
      await dir.create(recursive: true);
    }

    final int dot = source.path.lastIndexOf('.');
    final String extension = dot < 0 ? 'jpg' : source.path.substring(dot);

    return source.copy(
      '${dir.path}${Platform.pathSeparator}'
      '${DateTime.now().microsecondsSinceEpoch}$extension',
    );
  }

  Future<void> _capture(ImageSource source) async {
    if (_busy) {
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final XFile? shot = await _picker.pickImage(
        source: source,
        // The server accepts up to 4 MB, so compress well below that.
        maxWidth: 1280,
        maxHeight: 1280,
        imageQuality: 80,
        preferredCameraDevice: source == ImageSource.camera
            ? CameraDevice.front
            : CameraDevice.rear,
      );

      if (!mounted) {
        return;
      }

      if (shot == null) {
        setState(() => _busy = false);
        return;
      }

      _discardPending();
      _pending = await _stash(File(shot.path));

      if (!mounted) {
        _discardPending();
        return;
      }

      final File result = _pending!;
      _pending = null;

      Navigator.of(context).pop<File>(result);
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _busy = false;
        _error = 'Kamera tidak dapat diakses: $error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const Spacer(),
              Container(
                height: 110,
                width: 110,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: <Color>[
                      colors.primary.withValues(alpha: 0.18),
                      colors.primary.withValues(alpha: 0.02),
                    ],
                  ),
                ),
                child: Icon(
                  Icons.face_retouching_natural_rounded,
                  size: 56,
                  color: colors.primary,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Selfie wajib sebagai bukti kehadiran',
                style: theme.textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              Text(
                'Pastikan wajah terlihat jelas, pencahayaan cukup, dan tidak '
                'memakai filter atau masker.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
                textAlign: TextAlign.center,
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 20),
                ErrorBanner(message: _error!),
              ],
              const Spacer(),
              if (_busy) ...<Widget>[
                const LinearProgressIndicator(),
                const SizedBox(height: 16),
              ],
              GradientButton(
                label: 'Ambil dari kamera',
                icon: Icons.photo_camera_front_rounded,
                isLoading: _busy,
                onPressed: _busy ? null : () => _capture(ImageSource.camera),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _busy ? null : () => _capture(ImageSource.gallery),
                icon: const Icon(Icons.photo_library_outlined),
                label: const Text('Pilih dari galeri'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
