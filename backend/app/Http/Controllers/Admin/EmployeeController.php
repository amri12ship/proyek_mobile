<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with(['user', 'department', 'position'])
            ->when($request->string('q')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('nik', 'like', $search)
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $search));
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'statuses' => [Employee::STATUS_ACTIVE => 'Aktif', Employee::STATUS_INACTIVE => 'Nonaktif'],
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', [
            'employee' => new Employee(['status' => Employee::STATUS_ACTIVE]),
            'departments' => Department::orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'locations' => AttendanceLocation::orderBy('name')->get(),
            'selectedLocations' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $employee = DB::transaction(function () use ($request, $data): Employee {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'] ?? null,
                'password' => $data['password'],
                'role' => User::ROLE_EMPLOYEE,
                'is_active' => true,
                'phone' => $data['phone'] ?? null,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'nik' => $data['nik'],
                'name' => $data['name'],
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'status' => $data['status'] ?? Employee::STATUS_ACTIVE,
            ]);

            $this->syncLocations($employee, $request->input('locations', []), $request->input('primary_location'));

            return $employee;
        });

        $this->audit->success('employee.create', $request->user(), $employee, "Karyawan {$employee->name} dibuat");

        return redirect()->route('admin.employees.index')->with('success', "Karyawan {$employee->name} berhasil ditambahkan.");
    }

    public function edit(Employee $employee): View
    {
        $employee->load('user', 'locations');

        return view('admin.employees.form', [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'locations' => AttendanceLocation::orderBy('name')->get(),
            'selectedLocations' => $employee->locations->pluck('attendance_locations.id')->all(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);

        DB::transaction(function () use ($request, $employee, $data): void {
            $employee->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => ($data['status'] ?? Employee::STATUS_ACTIVE) === Employee::STATUS_ACTIVE,
            ]);

            if (! empty($data['password'])) {
                $employee->user->update(['password' => $data['password']]);
            }

            $employee->update([
                'nik' => $data['nik'],
                'name' => $data['name'],
                'department_id' => $data['department_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'status' => $data['status'] ?? Employee::STATUS_ACTIVE,
            ]);

            $this->syncLocations($employee, $request->input('locations', []), $request->input('primary_location'));
        });

        $this->audit->success('employee.update', $request->user(), $employee, "Karyawan {$employee->name} diperbarui");

        return redirect()->route('admin.employees.index')->with('success', "Data {$employee->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $name = $employee->name;
        $user = $employee->user;

        DB::transaction(function () use ($employee, $user): void {
            $employee->delete();
            $user->delete();
        });

        $this->audit->success('employee.delete', $request->user(), null, "Karyawan {$name} dihapus");

        return redirect()->route('admin.employees.index')->with('success', "Karyawan {$name} dihapus.");
    }

    public function toggle(Request $request, Employee $employee): RedirectResponse
    {
        $newStatus = $employee->isActive() ? Employee::STATUS_INACTIVE : Employee::STATUS_ACTIVE;

        $employee->update(['status' => $newStatus]);
        $employee->user->update(['is_active' => $newStatus === Employee::STATUS_ACTIVE]);

        if ($newStatus === Employee::STATUS_INACTIVE) {
            $employee->user->tokens()->delete();
        }

        $this->audit->success('employee.toggle', $request->user(), $employee, "Status {$employee->name} -> {$newStatus}");

        return back()->with('success', "Status {$employee->name} diubah menjadi {$newStatus}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        $id = $employee?->id;

        return $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('employees', 'nik')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($employee?->user_id)],
            'password' => [$employee === null ? 'required' : 'nullable', 'string', 'min:6', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:L,P'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['integer', 'exists:attendance_locations,id'],
            'primary_location' => ['nullable', 'integer', 'exists:attendance_locations,id'],
        ]);
    }

    /**
     * @param  array<int, mixed>  $locationIds
     */
    private function syncLocations(Employee $employee, array $locationIds, mixed $primaryId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $locationIds))));

        $employee->locations()->sync(array_map(
            static fn (int $id) => ['location_id' => $id, 'is_primary' => $id === (int) $primaryId],
            $ids,
        ));
    }
}
