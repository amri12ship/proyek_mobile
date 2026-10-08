import 'package:flutter/material.dart';

/// The single source of truth for the look and feel of the application.
///
/// The design language is deliberately restrained: an obsidian and ivory
/// neutral foundation, one metallic accent (champagne gold), and a serif
/// display face reserved for headings. Everything else is tonal, so the
/// product reads as a premium object rather than a colourful tool.
///
/// Every color, size, and component style is declared here so the app can be
/// re-branded from one place. Pages read `Theme.of(context)` and never hardcode
/// colors.
abstract final class AppTheme {
  const AppTheme._();

  /// Base seed the tonal palettes are generated from.
  static const Color seedColor = Color(0xFF7A5F2A);

  /// Serif face used only for display and headline text. On Android this
  /// resolves to the platform serif (Noto Serif), so no font has to be bundled.
  static const String displayFont = 'serif';

  /// Corner radius used by cards, inputs, and buttons.
  static const double cornerRadius = 22;

  /// Smaller corner radius used by chips and dense components.
  static const double cornerRadiusSmall = 12;

  /// Height of the main call to action buttons.
  static const double buttonHeight = 54;

  /// Shared light theme of the application (Material 3).
  static ThemeData get light => _build(Brightness.light);

  /// Shared dark theme of the application (Material 3).
  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final bool isDark = brightness == Brightness.dark;
    final ColorScheme colorScheme = _colorScheme(isDark);
    final AppBrand brand = isDark ? AppBrand.dark : AppBrand.light;

    final ThemeData base = ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      brightness: brightness,
    );

    return base.copyWith(
      scaffoldBackgroundColor: colorScheme.surface,
      extensions: <ThemeExtension<dynamic>>[brand],
      textTheme: _textTheme(base.textTheme, isDark),
      appBarTheme: _appBarTheme(colorScheme),
      cardTheme: _cardTheme(colorScheme),
      dialogTheme: _dialogTheme(colorScheme),
      bottomSheetTheme: _bottomSheetTheme(colorScheme),
      inputDecorationTheme: _inputDecorationTheme(colorScheme),
      elevatedButtonTheme: _elevatedButtonTheme(colorScheme),
      filledButtonTheme: _filledButtonTheme(colorScheme),
      outlinedButtonTheme: _outlinedButtonTheme(colorScheme),
      textButtonTheme: _textButtonTheme(colorScheme),
      navigationBarTheme: _navigationBarTheme(colorScheme),
      snackBarTheme: _snackBarTheme(colorScheme),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: colorScheme.primary,
        linearTrackColor: colorScheme.surfaceContainerHigh,
        circularTrackColor: colorScheme.surfaceContainerHigh,
        linearMinHeight: 4,
      ),
      dividerTheme: DividerThemeData(
        color: brand.hairline,
        thickness: 1,
        space: 1,
      ),
      chipTheme: _chipTheme(colorScheme),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: <TargetPlatform, PageTransitionsBuilder>{
          TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
        },
      ),
    );
  }

  static ColorScheme _colorScheme(bool isDark) {
    final ColorScheme generated = ColorScheme.fromSeed(
      seedColor: seedColor,
    );

    if (isDark) {
      return generated.copyWith(
        brightness: Brightness.dark,
        primary: const Color(0xFFDCC08C),
        onPrimary: const Color(0xFF1A1620),
        primaryContainer: const Color(0xFF3A2F1B),
        onPrimaryContainer: const Color(0xFFF1E1BF),
        secondary: const Color(0xFFC9B08A),
        onSecondary: const Color(0xFF1A1620),
        tertiary: const Color(0xFF9B8ACB),
        onTertiary: const Color(0xFF171225),
        error: const Color(0xFFCB7069),
        onError: const Color(0xFF1A1010),
        surface: const Color(0xFF0A0A0F),
        surfaceContainerLowest: const Color(0xFF0D0D13),
        surfaceContainerLow: const Color(0xFF14141C),
        surfaceContainer: const Color(0xFF1A1A24),
        surfaceContainerHigh: const Color(0xFF21212D),
        surfaceContainerHighest: const Color(0xFF2A2A38),
        onSurface: const Color(0xFFF3F1EC),
        onSurfaceVariant: const Color(0xFF9A96A6),
        outline: const Color(0xFF3A3646),
        outlineVariant: const Color(0xFF2A2634),
      );
    }

    return generated.copyWith(
      brightness: Brightness.light,
      primary: const Color(0xFF7A5F2A),
      onPrimary: const Color(0xFFFFFBF2),
      primaryContainer: const Color(0xFFF0E4CB),
      onPrimaryContainer: const Color(0xFF3E2F10),
      secondary: const Color(0xFF6B5636),
      onSecondary: Colors.white,
      tertiary: const Color(0xFF5F4C8F),
      onTertiary: Colors.white,
      error: const Color(0xFFB24A43),
      onError: Colors.white,
      surface: const Color(0xFFFBF8F3),
      surfaceContainerLowest: Colors.white,
      surfaceContainerLow: const Color(0xFFFFFFFF),
      surfaceContainer: const Color(0xFFF5F1E9),
      surfaceContainerHigh: const Color(0xFFEDE7DB),
      surfaceContainerHighest: const Color(0xFFE5DED0),
      onSurface: const Color(0xFF1B1A1F),
      onSurfaceVariant: const Color(0xFF63606B),
      outline: const Color(0xFFC9C1B2),
      outlineVariant: const Color(0xFFE3DCCF),
    );
  }

  static TextTheme _textTheme(TextTheme base, bool isDark) {
    final Color gold = isDark
        ? AppBrand.dark.gold
        : const Color(0xFF7A5F2A);

    TextStyle display(TextStyle? style, {required double size, double spacing = 0}) {
      return (style ?? const TextStyle()).copyWith(
        fontFamily: displayFont,
        fontSize: size,
        fontWeight: FontWeight.w700,
        letterSpacing: spacing,
        height: 1.15,
      );
    }

    return base.copyWith(
      displayLarge: display(base.displayLarge, size: 40, spacing: -0.8),
      displayMedium: display(base.displayMedium, size: 32, spacing: -0.6),
      displaySmall: display(base.displaySmall, size: 27, spacing: -0.4),
      headlineLarge: display(base.headlineLarge, size: 26, spacing: -0.4),
      headlineMedium: display(base.headlineMedium, size: 23, spacing: -0.3),
      headlineSmall: display(base.headlineSmall, size: 20, spacing: -0.2),
      titleLarge: base.titleLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.2,
        height: 1.25,
      ),
      titleMedium: base.titleMedium?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: 0,
      ),
      titleSmall: base.titleSmall?.copyWith(
        fontWeight: FontWeight.w600,
        color: gold,
        letterSpacing: 0.6,
      ),
      bodyLarge: base.bodyLarge?.copyWith(height: 1.55, letterSpacing: 0.1),
      bodyMedium: base.bodyMedium?.copyWith(height: 1.55, letterSpacing: 0.1),
      bodySmall: base.bodySmall?.copyWith(
        height: 1.5,
        letterSpacing: 0.2,
        color: isDark
            ? const Color(0xFF9A96A6)
            : const Color(0xFF6E6A78),
      ),
      labelLarge: base.labelLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: 0.8,
      ),
      labelMedium: base.labelMedium?.copyWith(fontWeight: FontWeight.w600),
      labelSmall: base.labelSmall?.copyWith(
        letterSpacing: 1.1,
        fontWeight: FontWeight.w600,
      ),
    );
  }

  static AppBarTheme _appBarTheme(ColorScheme colorScheme) {
    return AppBarTheme(
      backgroundColor: Colors.transparent,
      foregroundColor: colorScheme.onSurface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontFamily: displayFont,
        color: colorScheme.onSurface,
        fontSize: 21,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.2,
      ),
      iconTheme: IconThemeData(color: colorScheme.onSurfaceVariant),
    );
  }

  static CardThemeData _cardTheme(ColorScheme colorScheme) {
    return CardThemeData(
      elevation: 0,
      margin: EdgeInsets.zero,
      color: colorScheme.surfaceContainerLow,
      surfaceTintColor: Colors.transparent,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(cornerRadius),
      ),
    );
  }

  static DialogThemeData _dialogTheme(ColorScheme colorScheme) {
    return DialogThemeData(
      backgroundColor: colorScheme.surfaceContainerLow,
      surfaceTintColor: Colors.transparent,
      elevation: 24,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(cornerRadius + 6),
      ),
      titleTextStyle: TextStyle(
        fontFamily: displayFont,
        color: colorScheme.onSurface,
        fontSize: 21,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  static BottomSheetThemeData _bottomSheetTheme(ColorScheme colorScheme) {
    return BottomSheetThemeData(
      backgroundColor: colorScheme.surfaceContainerLow,
      surfaceTintColor: Colors.transparent,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
      ),
    );
  }

  static InputDecorationTheme _inputDecorationTheme(ColorScheme colorScheme) {
    final OutlineInputBorder border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(cornerRadius),
      borderSide: BorderSide(color: colorScheme.outlineVariant),
    );

    return InputDecorationTheme(
      filled: true,
      fillColor: colorScheme.surfaceContainerHigh,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      prefixIconColor: colorScheme.onSurfaceVariant,
      suffixIconColor: colorScheme.onSurfaceVariant,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: BorderSide(color: colorScheme.primary, width: 1.8),
      ),
      errorBorder: border.copyWith(
        borderSide: BorderSide(color: colorScheme.error),
      ),
      focusedErrorBorder: border.copyWith(
        borderSide: BorderSide(color: colorScheme.error, width: 1.8),
      ),
      border: border,
    );
  }

  static ButtonStyle _buttonStyle(ColorScheme colorScheme) {
    return ElevatedButton.styleFrom(
      minimumSize: const Size(0, buttonHeight),
      padding: const EdgeInsets.symmetric(horizontal: 26),
      textStyle: const TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 14,
        letterSpacing: 1.0,
      ),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(cornerRadius),
      ),
    );
  }

  static ButtonStyle _enabledStyle(ColorScheme colorScheme) {
    return _buttonStyle(colorScheme).copyWith(
      backgroundColor: WidgetStateProperty.resolveWith<Color>(
        (Set<WidgetState> states) => states.contains(WidgetState.disabled)
            ? colorScheme.surfaceContainerHigh
            : colorScheme.primary,
      ),
      foregroundColor: WidgetStateProperty.resolveWith<Color>(
        (Set<WidgetState> states) => states.contains(WidgetState.disabled)
            ? colorScheme.onSurfaceVariant.withValues(alpha: 0.5)
            : colorScheme.onPrimary,
      ),
    );
  }

  static ElevatedButtonThemeData _elevatedButtonTheme(ColorScheme colorScheme) {
    return ElevatedButtonThemeData(style: _enabledStyle(colorScheme));
  }

  static FilledButtonThemeData _filledButtonTheme(ColorScheme colorScheme) {
    return FilledButtonThemeData(style: _enabledStyle(colorScheme));
  }

  static TextButtonThemeData _textButtonTheme(ColorScheme colorScheme) {
    return TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: colorScheme.primary,
        textStyle: const TextStyle(
          fontWeight: FontWeight.w700,
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  static OutlinedButtonThemeData _outlinedButtonTheme(ColorScheme colorScheme) {
    return OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, buttonHeight),
        padding: const EdgeInsets.symmetric(horizontal: 26),
        textStyle: const TextStyle(
          fontWeight: FontWeight.w700,
          fontSize: 14,
          letterSpacing: 1.0,
        ),
        foregroundColor: colorScheme.onSurface,
        side: BorderSide(color: colorScheme.outlineVariant, width: 1.2),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(cornerRadius),
        ),
      ),
    );
  }

  static NavigationBarThemeData _navigationBarTheme(ColorScheme colorScheme) {
    return NavigationBarThemeData(
      backgroundColor: colorScheme.surfaceContainerLow,
      surfaceTintColor: Colors.transparent,
      indicatorColor: colorScheme.primary.withValues(alpha: 0.16),
      elevation: 0,
      height: 76,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      indicatorShape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(999),
      ),
      iconTheme: WidgetStateProperty.resolveWith<IconThemeData>(
        (Set<WidgetState> states) => IconThemeData(
          size: 23,
          color: states.contains(WidgetState.selected)
              ? colorScheme.primary
              : colorScheme.onSurfaceVariant,
        ),
      ),
      labelTextStyle: WidgetStateProperty.resolveWith<TextStyle>(
        (Set<WidgetState> states) => TextStyle(
          fontSize: 11,
          letterSpacing: 0.4,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected)
              ? colorScheme.primary
              : colorScheme.onSurfaceVariant,
        ),
      ),
    );
  }

  static SnackBarThemeData _snackBarTheme(ColorScheme colorScheme) {
    return SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      elevation: 10,
      backgroundColor: colorScheme.inverseSurface,
      contentTextStyle: TextStyle(
        color: colorScheme.onInverseSurface,
        fontWeight: FontWeight.w500,
        height: 1.4,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(cornerRadiusSmall),
      ),
    );
  }

  static ChipThemeData _chipTheme(ColorScheme colorScheme) {
    return ChipThemeData(
      backgroundColor: colorScheme.surfaceContainerHigh,
      side: BorderSide(color: colorScheme.outlineVariant),
      labelStyle: TextStyle(
        color: colorScheme.onSurfaceVariant,
        fontWeight: FontWeight.w600,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(999),
      ),
    );
  }
}

/// Brand colors that sit outside the tonal [ColorScheme] palette: the metallic
/// gold accent and the dark hero surface every top-of-page card is built on.
///
/// Registered as a [ThemeExtension] so pages keep reading colors from the theme
/// instead of hardcoding hex values.
@immutable
class AppBrand extends ThemeExtension<AppBrand> {
  const AppBrand({
    required this.gold,
    required this.hero,
    required this.accent,
    required this.success,
    required this.warning,
    required this.danger,
    required this.hairline,
  });

  /// Brand palette for the dark (default) appearance.
  static const AppBrand dark = AppBrand(
    gold: Color(0xFFDCC08C),
    hero: <Color>[
      Color(0xFF2B2440),
      Color(0xFF1C1826),
      Color(0xFF141119),
    ],
    accent: <Color>[Color(0xFFDCC08C), Color(0xFFB08D4F)],
    success: <Color>[Color(0xFF46B394), Color(0xFF2C8168)],
    warning: <Color>[Color(0xFFD9A45F), Color(0xFFB37C3C)],
    danger: <Color>[Color(0xFFCB7069), Color(0xFF9C4842)],
    hairline: Color(0xFF2C2836),
  );

  /// Brand palette for the light (ivory) appearance.
  static const AppBrand light = AppBrand(
    gold: Color(0xFF8A6A2C),
    hero: <Color>[
      Color(0xFF26213A),
      Color(0xFF15121D),
      Color(0xFF0F0D14),
    ],
    accent: <Color>[Color(0xFFE6CE9C), Color(0xFFB08D4F)],
    success: <Color>[Color(0xFF2F8E74), Color(0xFF1F6A56)],
    warning: <Color>[Color(0xFFB77B2E), Color(0xFF8C5A1E)],
    danger: <Color>[Color(0xFFB24A43), Color(0xFF8A332D)],
    hairline: Color(0xFFE8E2D7),
  );

  /// The metallic accent: primary buttons, icons, and hairline highlights.
  final Color gold;

  /// Always dark, in both appearances, so hero cards stay the darkest object
  /// on screen and read as a single material.
  final List<Color> hero;

  final List<Color> accent;
  final List<Color> success;
  final List<Color> warning;
  final List<Color> danger;

  /// Very light divider used to separate rows inside cards.
  final Color hairline;

  LinearGradient get heroGradient => LinearGradient(
    colors: hero,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  LinearGradient get goldGradient => LinearGradient(
    colors: accent,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  LinearGradient get successGradient => LinearGradient(
    colors: success,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  LinearGradient get warningGradient => LinearGradient(
    colors: warning,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  LinearGradient get dangerGradient => LinearGradient(
    colors: danger,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  /// Convenience accessor so pages can write `AppBrand.of(context).heroGradient`.
  static AppBrand of(BuildContext context) {
    return Theme.of(context).extension<AppBrand>() ?? AppBrand.dark;
  }

  @override
  AppBrand copyWith({
    Color? gold,
    List<Color>? hero,
    List<Color>? accent,
    List<Color>? success,
    List<Color>? warning,
    List<Color>? danger,
    Color? hairline,
  }) {
    return AppBrand(
      gold: gold ?? this.gold,
      hero: hero ?? this.hero,
      accent: accent ?? this.accent,
      success: success ?? this.success,
      warning: warning ?? this.warning,
      danger: danger ?? this.danger,
      hairline: hairline ?? this.hairline,
    );
  }

  @override
  AppBrand lerp(ThemeExtension<AppBrand>? other, double t) {
    if (other is! AppBrand) {
      return this;
    }

    return AppBrand(
      gold: Color.lerp(gold, other.gold, t) ?? gold,
      hero: _lerpList(hero, other.hero, t),
      accent: _lerpList(accent, other.accent, t),
      success: _lerpList(success, other.success, t),
      warning: _lerpList(warning, other.warning, t),
      danger: _lerpList(danger, other.danger, t),
      hairline: Color.lerp(hairline, other.hairline, t) ?? hairline,
    );
  }
}

List<Color> _lerpList(List<Color> a, List<Color> b, double t) {
  if (a.length != b.length) {
    return a;
  }

  return <Color>[
    for (int i = 0; i < a.length; i++) Color.lerp(a[i], b[i], t) ?? a[i],
  ];
}
