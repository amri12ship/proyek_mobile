import 'package:flutter/material.dart';

import '../../app/app_theme.dart';
import '../utils/responsive.dart';
import 'gradient_button.dart';

/// A white panel with a soft drop shadow, the base surface of every card.
///
/// The theme keeps [Card] flat and borderless, so depth is added here once
/// instead of being repeated in each page.
class SurfaceCard extends StatelessWidget {
  const SurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.color,
  });

  final Widget child;
  final EdgeInsets padding;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;
    final AppBrand brand = AppBrand.of(context);
    final bool isDark = theme.brightness == Brightness.dark;
    final Color surface = color ?? colors.surfaceContainerLow;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(AppTheme.cornerRadius),
        // A single hairline instead of a shadow stack: crisper on dark
        // surfaces, where shadows barely read.
        border: Border.all(color: brand.hairline),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: isDark
                ? Colors.black.withValues(alpha: 0.32)
                : const Color(0xFF2B2416).withValues(alpha: 0.07),
            blurRadius: isDark ? 24 : 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}

/// Constrains page content to a comfortable reading width and applies the
/// standard page padding, so phones, tablets, and desktop all look the same.
class PageBody extends StatelessWidget {
  const PageBody({super.key, required this.child, this.padding, this.maxWidth});

  final Widget child;
  final EdgeInsets? padding;
  final double? maxWidth;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: maxWidth ?? Responsive.tabletBreakpoint * 1.6,
        ),
        child: Padding(
          padding: padding ?? Responsive.pageInsetsOf(context),
          child: child,
        ),
      ),
    );
  }
}

/// A titled card with an optional trailing action, used across all pages.
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    required this.child,
    this.title,
    this.subtitle,
    this.icon,
    this.trailing,
    this.padding = const EdgeInsets.all(18),
  });

  final Widget child;
  final String? title;
  final String? subtitle;
  final IconData? icon;
  final Widget? trailing;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme colors = theme.colorScheme;
    final bool hasHeader = title != null || trailing != null;

    return SurfaceCard(
      padding: padding,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (hasHeader) ...<Widget>[
            Row(
              children: <Widget>[
                if (icon != null) ...<Widget>[
                  IconTile(icon: icon!),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      if (title != null)
                        Text(title!, style: theme.textTheme.titleMedium),
                      if (subtitle != null) ...<Widget>[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: colors.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (trailing != null) ?trailing,
              ],
            ),
            const SizedBox(height: 16),
          ],
          child,
        ],
      ),
    );
  }
}

/// Label and value row, the building block of the detail views.
class DetailRow extends StatelessWidget {
  const DetailRow({
    super.key,
    required this.label,
    required this.value,
    this.valueStyle,
    this.divider = false,
  });

  final String label;
  final String value;
  final TextStyle? valueStyle;

  /// Draws a hairline underneath so stacked rows read as a single list.
  final bool divider;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: BoxDecoration(
        border: divider
            ? Border(
                bottom: BorderSide(color: AppBrand.of(context).hairline),
              )
            : null,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            flex: 4,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 5,
            child: Text(
              value,
              style:
                  valueStyle ??
                  theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }
}
