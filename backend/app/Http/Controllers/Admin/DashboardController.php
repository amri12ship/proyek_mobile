<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function employee(): View
    {
        $user = auth()->user();
        $employee = $user->employee;
        $today = Carbon::today();

        $record = $employee === null ? null : AttendanceRecord::query()
            ->forDate($today)
            ->where('employee_id', $employee->id)
            ->first();

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $monthly = $employee === null ? collect() : AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->between($monthStart->toDateString(), $monthEnd->toDateString())
            ->get();

        $recent = $employee === null ? collect() : AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('attendance_date')
            ->limit(5)
            ->with('location')
            ->get();

        return view('dashboard', [
            'employee' => $employee,
            'record' => $record,
            'recent' => $recent,
            'statPresent' => $monthly->where('status', AttendanceRecord::STATUS_HADIR)->count(),
            'statLate' => $monthly->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
            'statMinutes' => (int) $monthly->sum('work_minutes'),
            'monthLabel' => $today->translatedFormat('F Y'),
        ]);
    }

    public function admin(): View
    {
        $today = Carbon::today();
        $employees = Employee::query()->where('status', Employee::STATUS_ACTIVE)->count();
        $inactive = Employee::query()->where('status', Employee::STATUS_INACTIVE)->count();

        $recordsToday = AttendanceRecord::query()->forDate($today)->get();

        return view('admin.dashboard', [
            'totalEmployees' => $employees,
            'inactiveEmployees' => $inactive,
            'presentToday' => $recordsToday->filter(fn ($r) => $r->hasCheckedIn())->count(),
            'absentToday' => max(0, $employees - $recordsToday->filter(fn ($r) => $r->hasCheckedIn())->count()),
            'lateToday' => $recordsToday->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
            'checkedOutToday' => $recordsToday->filter(fn ($r) => $r->isComplete())->count(),
            'todayLabel' => $today->translatedFormat('l, d F Y'),
            'recentRecords' => AttendanceRecord::query()
                ->with(['employee', 'location'])
                ->orderByDesc('check_in_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
