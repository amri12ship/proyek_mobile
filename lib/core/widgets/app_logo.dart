import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../app/app_theme.dart';
import 'lux_widgets.dart';

/// The application logo, drawn entirely with vectors so it stays razor sharp
/// at any size and never needs a rasterised asset.
///
/// The mark is an obsidian badge with a machined gold rim and a gold
/// fingerprint. Stacking the rim highlight, the top gloss, the lower inner
/// shadow, and the cast shadow is what makes it read as a physical metal
/// emblem rather than a flat coloured square.
///
/// On first mount a single specular sweep travels across the badge, which is
/// what sells it as polished metal. The sweep is finite so the widget still
/// settles for tests and reduced-motion users.
class AppLogo extends StatefulWidget {
  const AppLogo({
    super.key,
    this.size = 96,
    this.radiusFactor = 0.29,
    this.sweep = true,
  });

  final double size;

  /// Corner radius as a fraction of [size].
  final double radiusFactor;

  /// Plays the one-off specular sweep on mount.
  final bool sweep;

  @override
  State<AppLogo> createState() => _AppLogoState();
}

class _AppLogoState extends State<AppLogo> with SingleTickerProviderStateMixin {
  static const Duration _sweepDuration = Duration(milliseconds: 1900);

  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: _sweepDuration,
  );

  late final Animation<double> _progress = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeInOutCubic,
  );

  @override
  void initState() {
    super.initState();

    if (widget.sweep) {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: widget.size,
      width: widget.size,
      child: RepaintBoundary(
        child: AnimatedBuilder(
          animation: _progress,
          builder: (BuildContext context, Widget? child) => CustomPaint(
            painter: _AppLogoPainter(
              radiusFactor: widget.radiusFactor,
              sweep: _progress.value,
            ),
          ),
        ),
      ),
    );
  }
}

/// Compact horizontal lockup: logo plus wordmark, used on the login screen.
class AppLogoLockup extends StatelessWidget {
  const AppLogoLockup({
    super.key,
    this.logoSize = 76,
    this.title,
    this.subtitle,
    this.onDark = false,
  });

  final double logoSize;
  final String? title;
  final String? subtitle;

  /// Renders the text in light colours when placed on a dark surface.
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;
    final String? title = this.title;
    final String? subtitle = this.subtitle;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        AppLogo(size: logoSize),
        const SizedBox(width: 16),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (title != null)
                GoldText(
                  title,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: onDark ? Colors.white : colors.onSurface,
                  ),
                ),
              if (subtitle != null) ...<Widget>[
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: onDark
                        ? Colors.white.withValues(alpha: 0.7)
                        : colors.onSurfaceVariant,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _AppLogoPainter extends CustomPainter {
  const _AppLogoPainter({required this.radiusFactor, required this.sweep});

  final double radiusFactor;

  /// 0 before the specular sweep, 1 after it has passed off the badge.
  final double sweep;

  static const List<Color> _gold = <Color>[
    Color(0xFFF7E7BE),
    Color(0xFFDDBE83),
    Color(0xFFAE8A4B),
  ];

  static const List<Color> _obsidian = <Color>[
    Color(0xFF241F31),
    Color(0xFF17141F),
    Color(0xFF0C0A10),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final double s = size.shortestSide;
    final Rect bounds = Offset.zero & size;
    final Rect tile = bounds.deflate(s * 0.05);
    final double radius = s * radiusFactor;
    final RRect rrect = RRect.fromRectAndRadius(tile, Radius.circular(radius));
    final Path tilePath = Path()..addRRect(rrect);

    final Shader goldShader = LinearGradient(
      colors: _gold,
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
    ).createShader(tile);

    // Layered cast shadow: a wide soft one plus a tight contact shadow, so the
    // badge reads as floating above whatever it sits on.
    canvas.drawShadow(
      tilePath,
      const Color(0xFF000000).withValues(alpha: 0.55),
      s * 0.18,
      true,
    );
    canvas.drawShadow(
      tilePath,
      AppBrand.dark.gold.withValues(alpha: 0.28),
      s * 0.06,
      false,
    );

    // Obsidian body.
    canvas.drawRRect(
      rrect,
      Paint()
        ..shader = LinearGradient(
          colors: _obsidian,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ).createShader(tile),
    );

    canvas.save();
    canvas.clipPath(tilePath);

    // Warm bloom in the upper-left, as if lit from that side.
    canvas.drawRect(
      tile,
      Paint()
        ..shader = RadialGradient(
          center: const Alignment(-0.7, -0.9),
          radius: 1.15,
          colors: <Color>[
            AppBrand.dark.gold.withValues(alpha: 0.26),
            AppBrand.dark.gold.withValues(alpha: 0.0),
          ],
        ).createShader(tile),
    );

    // Glass gloss: a soft sheet fading out halfway down.
    final Rect glossRect = Rect.fromLTWH(
      tile.left,
      tile.top,
      tile.width,
      tile.height * 0.48,
    );
    canvas.drawRect(
      glossRect,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            Colors.white.withValues(alpha: 0.16),
            Colors.white.withValues(alpha: 0.0),
          ],
        ).createShader(glossRect),
    );

    // Lower inner shade lifts the silhouette off the background.
    final Rect shadeRect = Rect.fromLTWH(
      tile.left,
      tile.top + tile.height * 0.5,
      tile.width,
      tile.height * 0.5,
    );
    canvas.drawRect(
      shadeRect,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            Colors.black.withValues(alpha: 0.0),
            Colors.black.withValues(alpha: 0.45),
          ],
        ).createShader(shadeRect),
    );

    _paintFingerprint(canvas, tile, s, goldShader);

    // The one-off specular sweep travelling across the polished face. The
    // gradient is mapped onto a narrow moving band, so everything outside the
    // band resolves to fully transparent and nothing else is touched.
    if (sweep > 0 && sweep < 1) {
      final double bandCenter = tile.left + tile.width * (1.6 * sweep - 0.3);
      final double bandHalf = tile.width * 0.14;
      final Rect band = Rect.fromLTRB(
        bandCenter - bandHalf,
        tile.top,
        bandCenter + bandHalf,
        tile.bottom,
      );

      canvas.drawRect(
        tile,
        Paint()
          ..blendMode = BlendMode.plus
          ..shader = LinearGradient(
            colors: <Color>[
              Colors.white.withValues(alpha: 0.0),
              Colors.white.withValues(alpha: 0.22),
              Colors.white.withValues(alpha: 0.0),
            ],
            stops: const <double>[0, 0.5, 1],
          ).createShader(band),
      );
    }

    canvas.restore();

    // Machined gold rim: bright where the light catches, fading toward the
    // bottom so the edge looks bevelled rather than outlined.
    canvas.drawRRect(
      rrect.deflate(s * 0.014),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = s * 0.026
        ..shader = LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            const Color(0xFFF7E7BE),
            AppBrand.dark.gold.withValues(alpha: 0.55),
            AppBrand.dark.gold.withValues(alpha: 0.10),
          ],
          stops: const <double>[0, 0.4, 1],
        ).createShader(tile),
    );
  }

  /// Concentric arcs plus a short axis line: a readable fingerprint built from
  /// geometry rather than a font glyph, so it renders identically everywhere.
  void _paintFingerprint(Canvas canvas, Rect tile, double s, Shader gold) {
    final Offset center = Offset(tile.center.dx, tile.top + tile.height * 0.58);
    final double unit = tile.shortestSide;

    final Paint ridge = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = unit * 0.052
      ..shader = gold
      ..maskFilter = MaskFilter.blur(BlurStyle.normal, unit * 0.004);

    // The gap sits at the bottom so the ridges read as a fingerprint rather
    // than a bullseye: the sweep wraps left, top, and right.
    const double start = 0.7 * math.pi;
    const double arcSweep = 1.6 * math.pi;

    for (int i = 0; i < 5; i++) {
      final double r = unit * (0.15 + i * 0.093);
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: r),
        start,
        arcSweep,
        false,
        ridge,
      );
    }

    // Central axis: the tail of the print, running through the bottom gap.
    canvas.drawLine(
      Offset(center.dx, center.dy - unit * 0.02),
      Offset(center.dx, center.dy + unit * 0.30),
      ridge,
    );

    // Engraving pass: a dark hairline just inside each ridge makes the gold
    // look cut into the obsidian instead of floating on top of it.
    final Paint engrave = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = unit * 0.016
      ..color = Colors.black.withValues(alpha: 0.45);

    for (int i = 0; i < 5; i++) {
      final double r = unit * (0.15 + i * 0.093) - unit * 0.032;
      canvas.drawArc(
        Rect.fromCircle(
          center: center.translate(0, -unit * 0.008),
          radius: r,
        ),
        start,
        arcSweep,
        false,
        engrave,
      );
    }
  }

  @override
  bool shouldRepaint(_AppLogoPainter oldDelegate) =>
      oldDelegate.radiusFactor != radiusFactor || oldDelegate.sweep != sweep;
}
