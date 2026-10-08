import 'package:flutter/material.dart';

/// Device size buckets used to make layouts adapt to the screen width.
enum ScreenSize {
  /// Phones in portrait orientation, the primary target of this app.
  mobile,

  /// Large phones in landscape and small tablets.
  tablet,

  /// Tablets and large screens in landscape, plus desktop and web.
  desktop;

  bool get isMobile => this == ScreenSize.mobile;
  bool get isDesktopOrTablet => !isMobile;
}

/// Small layout helpers so pages do not repeat MediaQuery logic.
abstract final class Responsive {
  const Responsive._();

  /// Width below which the layout is treated as mobile.
  static const double tabletBreakpoint = 600;

  /// Width below which the layout is treated as tablet.
  static const double desktopBreakpoint = 840;

  static double widthOf(BuildContext context) =>
      MediaQuery.sizeOf(context).width;

  static ScreenSize sizeOf(BuildContext context) {
    final double width = widthOf(context);
    if (width < tabletBreakpoint) {
      return ScreenSize.mobile;
    }
    if (width < desktopBreakpoint) {
      return ScreenSize.tablet;
    }
    return ScreenSize.desktop;
  }

  /// Page padding: tighter on phones, roomier on larger screens.
  static EdgeInsets pageInsetsOf(
    BuildContext context, {
    double mobile = 16,
    double expanded = 24,
  }) {
    return EdgeInsets.all(
      sizeOf(context) == ScreenSize.mobile ? mobile : expanded,
    );
  }
}
