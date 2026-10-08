<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $query = AttendanceRecord::query()
            ->between($from->toDateString(), $to->toDateString())
            ->with('employee');

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $records = $query->orderBy('attendance_date')->get();

        $perEmployee = $records
            ->groupBy('employee_id')
            ->map(fn ($group) => [
                'employee' => $group->first()->employee,
                'hadir' => $group->where('status', AttendanceRecord::STATUS_HADIR)->count(),
                'terlambat' => $group->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
                'izin' => $group->where('status', AttendanceRecord::STATUS_IZIN)->count(),
                'sakit' => $group->where('status', AttendanceRecord::STATUS_SAKIT)->count(),
                'alpha' => $group->where('status', AttendanceRecord::STATUS_ALPHA)->count(),
                'minutes' => (int) $group->sum('work_minutes'),
            ])
            ->sortByDesc('terlambat')
            ->values();

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'rows' => $perEmployee,
            'totals' => [
                'hadir' => $perEmployee->sum('hadir'),
                'terlambat' => $perEmployee->sum('terlambat'),
                'izin' => $perEmployee->sum('izin'),
                'sakit' => $perEmployee->sum('sakit'),
                'alpha' => $perEmployee->sum('alpha'),
                'minutes' => $perEmployee->sum('minutes'),
            ],
            'departments' => Department::orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'status' => $request->string('status')->toString(),
            'departmentId' => $request->integer('department_id'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $records = AttendanceRecord::query()
            ->between($from->toDateString(), $to->toDateString())
            ->with('employee')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('attendance_date')
            ->get();

        $this->audit->success('report.export', $request->user(), null, "Ekspor laporan {$from} - {$to}");

        $filename = "laporan-absensi-{$from->format('Ymd')}-{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NIK', 'Nama', 'Tanggal', 'Masuk', 'Keluar', 'Status', 'Menit Kerja', 'Lokasi']);

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->employee?->nik,
                    $record->employee?->name,
                    $record->attendance_date?->toDateString(),
                    $record->check_in_at?->format('H:i'),
                    $record->check_out_at?->format('H:i'),
                    $this->statuses()[$record->status] ?? $record->status,
                    $record->work_minutes,
                    $record->location?->name,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $to = $request->date('to') ?? Carbon::today();
        $from = $request->date('from') ?? $to->copy()->startOfMonth();

        return [$from, $to];
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            AttendanceRecord::STATUS_HADIR => 'Hadir',
            AttendanceRecord::STATUS_TERLAMBAT => 'Terlambat',
            AttendanceRecord::STATUS_IZIN => 'Izin',
            AttendanceRecord::STATUS_SAKIT => 'Sakit',
            AttendanceRecord::STATUS_ALPHA => 'Alpha',
            AttendanceRecord::STATUS_LIBUR => 'Libur',
        ];
    }
}
