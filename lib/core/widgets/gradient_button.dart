import 'package:flutter/material.dart';

import '../../app/app_theme.dart';

/// Which brand gradient a [GradientButton] should paint.
enum ButtonGradient { hero, success, warning, danger }

/// Primary call to action rendered with a brand gradient.
///
/// [ElevatedButton] cannot express gradients through the [ThemeData], so this
/// widget fills the gap: it keeps the same height, radius, and disabled
/// behaviour as the themed buttons while looking far less flat.
class GradientButton extends StatelessWidget {
  const GradientButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.tone = ButtonGradient.hero,
    this.isLoading = false,
    this.height,
  });

  /// Button whose gradient follows the current status tone, e.g. green for a
  /// successful check-in and orange for check-out.
  const GradientButton.success({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.isLoading = false,
    this.height,
  }) : tone = ButtonGradient.success;

  const GradientButton.warning({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.isLoading = false,
    this.height,
  }) : tone = ButtonGradient.warning;

  const GradientButton.danger({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.isLoading = false,
    this.height,
  }) : tone = ButtonGradient.danger;

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final ButtonGradient tone;
  final bool isLoading;
  final double? height;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);
    final bool enabled = onPressed != null && !isLoading;
    final List<Color> colors = switch (tone) {
      ButtonGradient.hero => brand.goldGradient.colors,
      ButtonGradient.success => brand.success,
      ButtonGradient.warning => brand.warning,
      ButtonGradient.danger => brand.danger,
    };

    // The gold gradient is light, so it takes dark ink; the status gradients
    // are deep enough to carry white.
    final bool lightFill = tone == ButtonGradient.hero;
    final Color foreground = lightFill
        ? const Color(0xFF1A1620)
        : Colors.white;

    return Opacity(
      opacity: enabled ? 1 : 0.45,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppTheme.cornerRadius),
          gradient: enabled
              ? LinearGradient(
                  colors: colors,
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                )
              : null,
          color: enabled ? null : theme.colorScheme.surfaceContainerHigh,
          boxShadow: enabled
              ? <BoxShadow>[
                  BoxShadow(
                    color: colors.first.withValues(alpha: 0.32),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: enabled ? onPressed : null,
            borderRadius: BorderRadius.circular(AppTheme.cornerRadius),
            child: SizedBox(
              height: height ?? AppTheme.buttonHeight,
              child: Center(
                child: isLoading
                    ? SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: foreground,
                        ),
                      )
                    : Row(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          if (icon != null) ...<Widget>[
                            Icon(icon, size: 20, color: foreground),
                            const SizedBox(width: 10),
                          ],
                          Text(
                            label,
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: foreground,
                              fontSize: 15,
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Soft tinted square used to hold a leading icon inside cards and headers.
class IconTile extends StatelessWidget {
  const IconTile({
    super.key,
    required this.icon,
    this.color,
    this.size = 40,
    this.radius = 12,
  });

  final IconData icon;
  final Color? color;
  final double size;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final Color tint = color ?? AppBrand.of(context).gold;

    return Container(
      height: size,
      width: size,
      decoration: BoxDecoration(
        color: tint.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: tint.withValues(alpha: 0.22)),
      ),
      child: Icon(icon, size: size * 0.5, color: tint),
    );
  }
}
