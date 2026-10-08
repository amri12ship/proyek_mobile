<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\Employee;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.schedules.index', [
            'globalSchedules' => AttendanceSchedule::query()
                ->whereNull('employee_id')
                ->orderBy('day_of_week')
                ->get(),
            'personalSchedules' => AttendanceSchedule::query()
                ->whereNotNull('employee_id')
                ->with('employee')
                ->orderBy('day_of_week')
                ->get(),
            'employees' => Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('name')->get(),
            'days' => $this->days(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'is_workday' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ]);

        AttendanceSchedule::updateOrCreate(
            [
                'employee_id' => $data['employee_id'] ?? null,
                'day_of_week' => $data['day_of_week'],
            ],
            [
                'is_workday' => $request->boolean('is_workday', true),
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
            ],
        );

        $this->audit->success('schedule.upsert', $request->user(), null, 'Jadwal kerja disimpan');

        return back()->with('success', 'Jadwal kerja disimpan.');
    }

    public function destroy(Request $request, AttendanceSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        $this->audit->success('schedule.delete', $request->user(), null, 'Jadwal kerja dihapus');

        return back()->with('success', 'Jadwal kerja dihapus.');
    }

    /**
     * @return array<int, string>
     */
    private function days(): array
    {
        return [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    }
}
