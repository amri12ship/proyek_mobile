<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $date = $request->date('date') ?? Carbon::today();
        $status = $request->string('status')->toString();

        $records = AttendanceRecord::query()
            ->forDate($date)
            ->with(['employee.department', 'location'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('check_in_at')
            ->paginate(20)
            ->withQueryString();

        $allOfDay = AttendanceRecord::query()->forDate($date)->get();
        $employees = Employee::query()->where('status', Employee::STATUS_ACTIVE)->count();

        return view('admin.attendance.index', [
            'records' => $records,
            'date' => $date,
            'status' => $status,
            'statuses' => $this->statuses(),
            'present' => $allOfDay->filter(fn ($r) => $r->hasCheckedIn())->count(),
            'late' => $allOfDay->where('status', AttendanceRecord::STATUS_TERLAMBAT)->count(),
            'complete' => $allOfDay->filter(fn ($r) => $r->isComplete())->count(),
            'totalEmployees' => $employees,
            'absent' => max(0, $employees - $allOfDay->filter(fn ($r) => $r->hasCheckedIn())->count()),
        ]);
    }

    public function show(AttendanceRecord $record): View
    {
        return view('admin.attendance.show', [
            'record' => $record->load(['employee.department', 'employee.position', 'location', 'validation']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function updateStatus(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $record->update($data);

        $this->audit->success('attendance.override', $request->user(), $record->employee, "Status absensi {$record->employee?->name} -> {$data['status']}");

        return back()->with('success', 'Status absensi diperbarui.');
    }

    /**
     * Streams a stored selfie straight from the private disk. Never exposed
     * through a public URL, so it stays behind the admin role middleware.
     */
    public function selfie(Request $request, AttendanceRecord $record, string $type): BinaryFileResponse
    {
        $path = $type === 'check-in' ? $record->check_in_selfie : $record->check_out_selfie;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        $this->audit->success('attendance.selfie.view', $request->user(), $record->employee, "Selfie $type dibuka: {$record->employee?->name}");

        $response = response()->file(Storage::disk('local')->path($path));

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
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
