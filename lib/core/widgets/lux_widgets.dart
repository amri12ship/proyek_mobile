import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../app/app_theme.dart';

/// The signature surface of the app: a deep, near-black panel with a hairline
/// gold rim and a soft metallic sheen.
///
/// Used for the brand moments (login header, greeting, profile, ticket) so
/// those screens share one recognisable material instead of repeating
/// gradient code in every page.
class HeroCard extends StatelessWidget {
  const HeroCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(22),
    this.borderRadius,
    this.glow = true,
  });

  final Widget child;
  final EdgeInsets padding;
  final double? borderRadius;

  /// Adds the warm bloom that lifts the panel off the background.
  final bool glow;

  @override
  Widget build(BuildContext context) {
    final AppBrand brand = AppBrand.of(context);
    final double radius = borderRadius ?? AppTheme.cornerRadius;

    return Container(
      padding: padding,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(radius),
        gradient: brand.heroGradient,
        boxShadow: glow
            ? <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withValues(
                    alpha: Theme.of(context).brightness == Brightness.dark
                        ? 0.45
                        : 0.18,
                  ),
                  blurRadius: 30,
                  offset: const Offset(0, 14),
                ),
                BoxShadow(
                  color: brand.gold.withValues(alpha: 0.10),
                  blurRadius: 40,
                  spreadRadius: -12,
                ),
              ]
            : null,
      ),
      child: Stack(
        children: <Widget>[
          // Hairline gold rim plus a very faint metallic wash. Painted before
          // the content so it never washes out the text on top of it.
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(radius),
                border: Border.all(color: brand.gold.withValues(alpha: 0.30)),
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[
                    brand.gold.withValues(alpha: 0.16),
                    brand.gold.withValues(alpha: 0.03),
                    Colors.transparent,
                  ],
                  stops: const <double>[0, 0.4, 0.8],
                ),
              ),
            ),
          ),
          child,
        ],
      ),
    );
  }
}

/// Text filled with the metallic gradient, used sparingly for brand moments.
class GoldText extends StatelessWidget {
  const GoldText(
    this.data, {
    super.key,
    this.style,
    this.textAlign,
    this.maxLines,
  });

  final String data;
  final TextStyle? style;
  final TextAlign? textAlign;
  final int? maxLines;

  @override
  Widget build(BuildContext context) {
    final AppBrand brand = AppBrand.of(context);

    return ShaderMask(
      blendMode: BlendMode.srcIn,
      shaderCallback: (Rect bounds) =>
          brand.goldGradient.createShader(bounds),
      child: Text(
        data,
        style: style,
        textAlign: textAlign,
        maxLines: maxLines,
      ),
    );
  }
}

/// A hairline rule with a metallic falloff, used between premium sections.
class GoldRule extends StatelessWidget {
  const GoldRule({super.key, this.height = 1});

  final double height;

  @override
  Widget build(BuildContext context) {
    final AppBrand brand = AppBrand.of(context);

    return Container(
      height: height,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            Colors.transparent,
            brand.gold.withValues(alpha: 0.35),
            Colors.transparent,
          ],
        ),
      ),
    );
  }
}

/// Frosted glass panel, for floating content that should feel suspended above
/// the page rather than pasted onto it.
class FrostedPanel extends StatelessWidget {
  const FrostedPanel({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.borderRadius,
    this.blur = 18,
  });

  final Widget child;
  final EdgeInsets padding;
  final double? borderRadius;
  final double blur;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);
    final double radius = borderRadius ?? AppTheme.cornerRadius;
    final bool isDark = theme.brightness == Brightness.dark;

    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(radius),
            color: isDark
                ? const Color(0xFF15151E).withValues(alpha: 0.72)
                : Colors.white.withValues(alpha: 0.66),
            border: Border.all(
              color: isDark
                  ? brand.gold.withValues(alpha: 0.18)
                  : Colors.white.withValues(alpha: 0.7),
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}

/// Small uppercase caption in the metallic accent, used to label sections
/// without adding another heading level.
class Eyebrow extends StatelessWidget {
  const Eyebrow(this.label, {super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    final AppBrand brand = AppBrand.of(context);

    return GoldText(
      label.toUpperCase(),
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
        color: brand.gold,
        letterSpacing: 2.0,
        fontWeight: FontWeight.w700,
      ),
    );
  }
}
