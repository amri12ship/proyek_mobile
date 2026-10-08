import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/state/attendance_controller.dart';
import '../../../core/state/session_controller.dart';
import '../../../core/state/theme_controller.dart';
import '../../../core/widgets/gradient_button.dart';
import '../../../core/widgets/lux_widgets.dart';
import '../../../core/widgets/page_body.dart';

/// Profile, server configuration, and sign-out.
class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key});

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  late final TextEditingController _baseUrl = TextEditingController(
    text: context.read<SessionController>().baseUrl,
  );

  @override
  void dispose() {
    _baseUrl.dispose();
    super.dispose();
  }

  Future<void> _saveBaseUrl() async {
    final SessionController session = context.read<SessionController>();
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final FocusNode focus = FocusScope.of(context);

    final bool ok = await session.updateBaseUrl(_baseUrl.text);

    focus.unfocus();

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Alamat server disimpan. Silakan masuk kembali.'
              : (session.error ?? 'Alamat server tidak dapat disimpan.'),
        ),
      ),
    );

    if (ok && mounted) {
      _baseUrl.text = session.baseUrl;
    }
  }

  Future<void> _logout() async {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    context.read<AttendanceController>().reset();
    await context.read<SessionController>().logout();

    messenger.hideCurrentSnackBar();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final SessionController session = context.watch<SessionController>();
    final user = session.user;

    return PageBody(
      child: ListView(
        children: <Widget>[
          _ProfileHero(
            name: user?.name ?? 'Karyawan',
            initials: user?.initials ?? '?',
            subtitle: user?.department ?? user?.email ?? '-',
          ),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Detail akun',
            icon: Icons.badge_rounded,
            child: Column(
              children: <Widget>[
                DetailRow(label: 'Nama', value: user?.name ?? '-', divider: true),
                DetailRow(
                  label: 'Email',
                  value: user?.email ?? '-',
                  divider: user?.nik != null,
                ),
                if (user?.nik != null)
                  DetailRow(
                    label: 'NIK',
                    value: user!.nik!,
                    divider: user.department != null,
                  ),
                if (user?.department != null)
                  DetailRow(
                    label: 'Departemen',
                    value: user!.department!,
                    divider: user.position != null,
                  ),
                if (user?.position != null)
                  DetailRow(
                    label: 'Jabatan',
                    value: user!.position!,
                    divider: user.phone != null,
                  ),
                if (user?.phone != null)
                  DetailRow(
                    label: 'Telepon',
                    value: user!.phone!,
                    divider: true,
                  ),
                DetailRow(label: 'Peran', value: user?.role ?? '-'),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const _AppearanceCard(),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Server API',
            subtitle: 'Ubah jika alamat IP server berubah.',
            icon: Icons.dns_rounded,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                TextField(
                  controller: _baseUrl,
                  keyboardType: TextInputType.url,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Alamat server',
                    hintText: 'http://192.168.1.10:8000',
                    prefixIcon: Icon(Icons.link_rounded),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Default: ${session.defaultBaseUrl}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 16),
                GradientButton(
                  label: 'Simpan & keluar',
                  icon: Icons.save_rounded,
                  onPressed: _saveBaseUrl,
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () async {
                    final SessionController session = context
                        .read<SessionController>();

                    await session.resetBaseUrl();

                    if (mounted) {
                      _baseUrl.text = session.baseUrl;
                    }
                  },
                  child: const Text('Kembalikan ke default'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: session.isSubmitting ? null : _logout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Keluar'),
            style: OutlinedButton.styleFrom(
              foregroundColor: theme.colorScheme.error,
              side: BorderSide(
                color: theme.colorScheme.error.withValues(alpha: 0.4),
                width: 1.5,
              ),
            ),
          ),
          const SizedBox(height: 24),
          Center(
            child: Text(
              'Absensi Karyawan',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Light / dark selector, styled as two metallic swatches so it reads as part
/// of the same design language rather than a stock Material toggle.
class _AppearanceCard extends StatelessWidget {
  const _AppearanceCard();

  @override
  Widget build(BuildContext context) {
    final ThemeController controller = context.watch<ThemeController>();

    return SectionCard(
      title: 'Tampilan',
      subtitle: 'Pilih tema gelap atau ivory.',
      icon: Icons.palette_outlined,
      child: Row(
        children: <Widget>[
          Expanded(
            child: _SwatchButton(
              label: 'Gelap',
              icon: Icons.dark_mode_rounded,
              selected: controller.mode == ThemeMode.dark,
              preview: const <Color>[
                Color(0xFF241F31),
                Color(0xFF0C0A10),
              ],
              onTap: () => controller.setMode(ThemeMode.dark),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _SwatchButton(
              label: 'Ivory',
              icon: Icons.light_mode_rounded,
              selected: controller.mode == ThemeMode.light,
              preview: const <Color>[
                Color(0xFFFDFBF6),
                Color(0xFFEDE7DB),
              ],
              onTap: () => controller.setMode(ThemeMode.light),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _SwatchButton(
              label: 'Sistem',
              icon: Icons.brightness_auto_rounded,
              selected: controller.mode == ThemeMode.system,
              preview: const <Color>[
                Color(0xFF241F31),
                Color(0xFFFDFBF6),
              ],
              onTap: () => controller.setMode(ThemeMode.system),
            ),
          ),
        ],
      ),
    );
  }
}

class _SwatchButton extends StatelessWidget {
  const _SwatchButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.preview,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final List<Color> preview;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AppBrand brand = AppBrand.of(context);
    final ColorScheme colors = theme.colorScheme;

    return Semantics(
      button: true,
      selected: selected,
      label: 'Tema $label',
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppTheme.cornerRadiusSmall),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppTheme.cornerRadiusSmall),
            color: selected
                ? brand.gold.withValues(alpha: 0.12)
                : colors.surfaceContainerHigh,
            border: Border.all(
              color: selected
                  ? brand.gold.withValues(alpha: 0.7)
                  : colors.outlineVariant,
              width: selected ? 1.4 : 1,
            ),
          ),
          child: Column(
            children: <Widget>[
              Container(
                height: 40,
                width: 40,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(10),
                  gradient: LinearGradient(
                    colors: preview,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  border: Border.all(
                    color: colors.outlineVariant.withValues(alpha: 0.6),
                  ),
                ),
                child: Icon(
                  icon,
                  size: 18,
                  color: selected
                      ? brand.gold
                      : colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                label,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: selected ? brand.gold : colors.onSurfaceVariant,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Gradient header shown at the top of the settings tab.
class _ProfileHero extends StatelessWidget {
  const _ProfileHero({
    required this.name,
    required this.initials,
    required this.subtitle,
  });

  final String name;
  final String initials;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return HeroCard(
      child: Row(
        children: <Widget>[
          Container(
            height: 60,
            width: 60,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: AppBrand.of(context).gold.withValues(alpha: 0.45),
                width: 1.2,
              ),
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  AppBrand.of(context).gold.withValues(alpha: 0.28),
                  AppBrand.of(context).gold.withValues(alpha: 0.06),
                ],
              ),
            ),
            child: Text(
              initials,
              style: theme.textTheme.headlineSmall?.copyWith(
                color: Colors.white,
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  name,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.7),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
