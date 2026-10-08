import 'package:flutter/material.dart';

/// Small coloured pill used for attendance and validation status.
class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.label,
    required this.tone,
    this.icon,
  });

  /// Neutral chip for information the employee has not acted on yet.
  const StatusBadge.neutral({super.key, required this.label, this.icon})
    : tone = BadgeTone.neutral;

  /// Positive chip for a successful state.
  const StatusBadge.success({super.key, required this.label, this.icon})
    : tone = BadgeTone.success;

  /// Warning chip for a state that still allows an action.
  const StatusBadge.warning({super.key, required this.label, this.icon})
    : tone = BadgeTone.warning;

  /// Negative chip for a blocked or failed state.
  const StatusBadge.danger({super.key, required this.label, this.icon})
    : tone = BadgeTone.danger;

  final String label;
  final BadgeTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final (Color background, Color foreground, Color outline) =
        switch (tone) {
          BadgeTone.neutral => (
            colors.surfaceContainerHigh,
            colors.onSurfaceVariant,
            colors.outlineVariant,
          ),
          BadgeTone.success => (
            colors.primaryContainer,
            colors.onPrimaryContainer,
            colors.primary.withValues(alpha: 0.25),
          ),
          BadgeTone.warning => (
            colors.tertiaryContainer,
            colors.onTertiaryContainer,
            colors.tertiary.withValues(alpha: 0.28),
          ),
          BadgeTone.danger => (
            colors.errorContainer,
            colors.onErrorContainer,
            colors.error.withValues(alpha: 0.28),
          ),
        };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: outline),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icon != null) ...<Widget>[
            Icon(icon, size: 15, color: foreground),
            const SizedBox(width: 6),
          ],
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

/// The semantic meaning of a [StatusBadge].
enum BadgeTone { neutral, success, warning, danger }
