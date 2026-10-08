import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/state/session_controller.dart';
import '../../../core/widgets/app_logo.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/gradient_button.dart';
import '../../../core/widgets/lux_widgets.dart';
import '../../../core/widgets/page_body.dart';

/// Email and password form. On success the [SessionController] swap in
/// [AbsensiApp] takes over, so this page never navigates by itself.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();

  bool _obscurePassword = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    FocusScope.of(context).unfocus();

    await context.read<SessionController>().login(
      email: _email.text,
      password: _password.text,
      deviceName: _deviceName,
    );
  }

  String get _deviceName =>
      Platform.isAndroid ? 'Android' : (Platform.isIOS ? 'iOS' : 'Mobile');

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final SessionController session = context.watch<SessionController>();
    final bool busy = session.isSubmitting;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(
                maxWidth: AppConstants.maxContentWidth,
              ),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    _Header(theme: theme),
                    const SizedBox(height: 28),
                    if (session.error != null) ...<Widget>[
                      ErrorBanner(
                        message: session.error!,
                        onDismiss: session.clearError,
                      ),
                      const SizedBox(height: 20),
                    ],
                    SurfaceCard(
                      padding: const EdgeInsets.fromLTRB(22, 24, 22, 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          const Eyebrow('Masuk ke akun'),
                          const SizedBox(height: 18),
                          TextFormField(
                            controller: _email,
                            enabled: !busy,
                            keyboardType: TextInputType.emailAddress,
                            textInputAction: TextInputAction.next,
                            textCapitalization: TextCapitalization.none,
                            autocorrect: false,
                            autofillHints: const <String>[AutofillHints.email],
                            decoration: const InputDecoration(
                              labelText: 'Email',
                              hintText: 'nama@perusahaan.com',
                              prefixIcon: Icon(Icons.mail_outline_rounded),
                            ),
                            validator: _validateEmail,
                          ),
                          const SizedBox(height: 14),
                          TextFormField(
                            controller: _password,
                            enabled: !busy,
                            obscureText: _obscurePassword,
                            textInputAction: TextInputAction.done,
                            autofillHints: const <String>[AutofillHints.password],
                            onFieldSubmitted: (_) => _submit(),
                            decoration: InputDecoration(
                              labelText: 'Password',
                              prefixIcon: const Icon(
                                Icons.lock_outline_rounded,
                              ),
                              suffixIcon: IconButton(
                                onPressed: () => setState(
                                  () => _obscurePassword = !_obscurePassword,
                                ),
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                ),
                                tooltip: _obscurePassword
                                    ? 'Tampilkan password'
                                    : 'Sembunyikan password',
                              ),
                            ),
                            validator: (String? value) {
                              if ((value ?? '').length < 6) {
                                return 'Password minimal 6 karakter.';
                              }

                              return null;
                            },
                          ),
                          const SizedBox(height: 24),
                          GradientButton(
                            label: 'Masuk',
                            icon: Icons.login_rounded,
                            isLoading: busy,
                            onPressed: busy ? null : _submit,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    _Footer(session: session),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  static String? _validateEmail(String? value) {
    final String email = (value ?? '').trim();

    if (email.isEmpty) {
      return 'Email wajib diisi.';
    }

    if (!email.contains('@') || !email.contains('.')) {
      return 'Format email tidak valid.';
    }

    return null;
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.theme});

  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    // Scale the brand block down on short screens so the form stays reachable
    // without scrolling on small phones.
    final bool compact = MediaQuery.sizeOf(context).height < 700;

    return HeroCard(
      padding: EdgeInsets.symmetric(
        vertical: compact ? 28 : 40,
        horizontal: 26,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          Center(child: AppLogo(size: compact ? 76 : 96)),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            child: GoldText(
              AppConstants.appName,
              style: theme.textTheme.headlineSmall,
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: Text(
              AppConstants.appDescription,
              style: theme.textTheme.bodySmall?.copyWith(
                color: Colors.white.withValues(alpha: 0.7),
              ),
              textAlign: TextAlign.center,
            ),
          ),
        ],
      ),
    );
  }
}

/// Connection details and the network requirement, kept out of the form card
/// so the form itself stays clean.
class _Footer extends StatelessWidget {
  const _Footer({required this.session});

  final SessionController session;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);

    return Column(
      children: <Widget>[
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppTheme.cornerRadiusSmall),
            color: theme.colorScheme.surfaceContainer,
            border: Border.all(color: brand.hairline),
          ),
          child: Row(
            children: <Widget>[
              Icon(
                session.isBaseUrlOverridden
                    ? Icons.dns_rounded
                    : Icons.wifi_tethering_rounded,
                size: 18,
                color: session.isBaseUrlOverridden
                    ? theme.colorScheme.tertiary
                    : brand.gold,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      session.isBaseUrlOverridden
                          ? 'Server khusus'
                          : 'Server aktif',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      session.baseUrl,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurface,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              Icons.wifi_rounded,
              size: 14,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                'Perangkat harus terhubung ke Wi-Fi yang sama dengan server.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
