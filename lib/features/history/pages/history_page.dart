import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../app/app_theme.dart';
import '../../../core/models/attendance_record.dart';
import '../../../core/services/attendance_repository.dart';
import '../../../core/state/attendance_controller.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/gradient_button.dart';
import '../../../core/widgets/page_body.dart';
import '../../../core/widgets/status_badge.dart';

/// Attendance history for a selectable date range.
class HistoryPage extends StatefulWidget {
  const HistoryPage({super.key});

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  late DateTimeRange _range = DateTimeRange(
    start: DateTime.now().subtract(const Duration(days: 29)),
    end: DateTime.now(),
  );

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _load();
      }
    });
  }

  Future<void> _load() {
    return context.read<AttendanceController>().loadHistory(
      range: DateTimeRangeLike(from: _range.start, to: _range.end),
    );
  }

  Future<void> _pickRange() async {
    final DateTime now = DateTime.now();
    final DateTimeRange? picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 2),
      lastDate: now,
      initialDateRange: _range,
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() => _range = picked);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final AttendanceController controller = context
        .watch<AttendanceController>();
    final AttendanceHistory? history = controller.history;
    final DateFormat rangeFormat = DateFormat('d MMM yyyy', 'id');

    return RefreshIndicator(
      onRefresh: _load,
      child: PageBody(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SurfaceCard(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
              child: ListTile(
                onTap: _pickRange,
                contentPadding: EdgeInsets.zero,
                leading: const IconTile(
                  icon: Icons.date_range_rounded,
                  size: 40,
                ),
                title: Text(
                  'Rentang tanggal',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
                subtitle: Text(
                  '${rangeFormat.format(_range.start)} - '
                  '${rangeFormat.format(_range.end)}',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                trailing: const Icon(Icons.chevron_right_rounded),
              ),
            ),
            const SizedBox(height: 16),
            Expanded(child: _buildBody(controller, history)),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(
    AttendanceController controller,
    AttendanceHistory? history,
  ) {
    if (controller.isLoading && history == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (controller.error != null && history == null) {
      return ErrorView(message: controller.error!, onRetry: _load);
    }

    final List<AttendanceRecord> records =
        history?.records ?? const <AttendanceRecord>[];

    if (records.isEmpty) {
      return const EmptyView(
        title: 'Belum ada data',
        message: 'Tidak ada catatan kehadiran pada rentang tanggal ini.',
        icon: Icons.event_busy_rounded,
      );
    }

    return ListView.separated(
      itemCount: records.length + 1,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (BuildContext context, int index) {
        if (index == 0) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Text(
              '${history!.total} catatan',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          );
        }

        return _HistoryTile(record: records[index - 1]);
      },
    );
  }
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.record});

  final AttendanceRecord record;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final DateTime? day = DateTime.tryParse(record.date);

    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (day != null) ...<Widget>[
            Container(
              width: 52,
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(
                color: theme.colorScheme.surfaceContainer,
                borderRadius: BorderRadius.circular(
                  AppTheme.cornerRadiusSmall,
                ),
              ),
              child: Column(
                children: <Widget>[
                  Text(
                    DateFormat('d', 'id').format(day),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: theme.colorScheme.primary,
                    ),
                  ),
                  Text(
                    DateFormat('MMM', 'id').format(day),
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  day == null ? record.date : Formatters.shortDate(day),
                  style: theme.textTheme.titleSmall,
                ),
                const SizedBox(height: 4),
                Text(
                  '${Formatters.time(record.checkIn)} - '
                  '${Formatters.time(record.checkOut)}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (record.locationName != null) ...<Widget>[
                  const SizedBox(height: 2),
                  Text(
                    record.locationName!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: <Widget>[
                    StatusBadge(
                      label: record.statusLabel,
                      tone: record.isLate
                          ? BadgeTone.warning
                          : BadgeTone.success,
                    ),
                    if (record.hasSelfie)
                      const StatusBadge.neutral(
                        label: 'Selfie',
                        icon: Icons.face_rounded,
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text(record.workLabel, style: theme.textTheme.titleMedium),
              const SizedBox(height: 2),
              Text(
                Formatters.distance(record.checkInDistance),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
