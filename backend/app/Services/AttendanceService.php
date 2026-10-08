<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Api\V1\SelfieController;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\Holiday;
use App\Support\Geo;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function __construct(
        private readonly LocationValidationService $validations,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Snapshot of the employee's attendance state for a given day.
     */
    public function today(Employee $employee, ?Carbon $date = null): array
    {
        $setting = AttendanceSetting::current();
        $timezone = $this->resolveTimezone($setting);
        $date ??= now($timezone);
        $record = AttendanceRecord::query()
            ->forDate($date)
            ->where('employee_id', $employee->id)
            ->first();

        return [
            'date' => $date->toDateString(),
            'server_time' => now($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'status' => $this->stateOf($record),
            'can_check_in' => $this->canCheckIn($employee, $record, $date),
            'can_check_out' => $record !== null && $record->hasCheckedIn() && ! $record->hasCheckedOut(),
            'is_workday' => $this->isWorkday($employee, $date),
            'is_holiday' => Holiday::isHoliday($date),
            'schedule' => $this->scheduleFor($employee, $date),
            'work_hours' => [
                'start' => substr((string) $setting->work_start, 0, 5),
                'end' => substr((string) $setting->work_end, 0, 5),
                'tolerance_minutes' => $setting->tolerance_minutes,
            ],
            'validation' => [
                'ticket_ttl_seconds' => $setting->ticket_ttl_seconds,
                'require_selfie' => $setting->require_selfie,
            ],
            'record' => $record,
        ];
    }

    /**
     * Check in: requires a valid single-use ticket plus a GPS position inside the radius.
     */
    public function checkIn(Employee $employee, array $context): AttendanceRecord
    {
        $setting = AttendanceSetting::current();
        $timezone = $this->resolveTimezone($setting);
        $date = now($timezone);

        if (! $employee->isActive()) {
            $this->audit->failed('attendance.check_in', $employee->user, $employee, 'Karyawan nonaktif');

            throw AttendanceException::make('Akun Anda tidak aktif.', 'EMPLOYEE_INACTIVE', 403);
        }

        $record = AttendanceRecord::query()
            ->forDate($date)
            ->where('employee_id', $employee->id)
            ->first();

        if ($record !== null && $record->hasCheckedIn()) {
            $this->audit->failed('attendance.check_in', $employee->user, $employee, 'Sudah check-in hari ini');

            throw AttendanceException::make(
                'Anda sudah check-in hari ini.',
                'ALREADY_CHECKED_IN',
                409,
                ['check_in_at' => $record->check_in_at?->toIso8601String()],
            );
        }

        $ticket = $this->requireTicket($context);
        $validation = $this->validations->consume($ticket, $employee);
        $location = $validation->location;

        $distance = $this->validations->assertWithinRadius($employee, $location, $context);

        $this->assertWorkingHours($employee, $date, $setting);

        $selfie = $this->resolveSelfie($employee, $setting, $context, 'check-in');

        $status = $this->resolveStatus($date, $setting);

        $record ??= new AttendanceRecord([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
        ]);

        $record->fill([
            'location_id' => $location->id,
            'location_validation_id' => $validation->id,
            'check_in_at' => now($timezone),
            'check_in_latitude' => $context['latitude'] ?? null,
            'check_in_longitude' => $context['longitude'] ?? null,
            'check_in_distance' => $distance,
            'check_in_accuracy' => $context['accuracy'] ?? null,
            'check_in_selfie' => $selfie,
            'status' => $record->exists && $record->status !== AttendanceRecord::STATUS_HADIR
                ? $record->status
                : $status,
        ])->save();

        $this->audit->success(
            'attendance.check_in',
            $employee->user,
            $employee,
            sprintf('Check-in di %s (%.2f m)', $location->name, $distance),
        );

        return $record->load(['location', 'validation']);
    }

    /**
     * Check out: requires an existing check-in, GPS inside the radius, and optionally a selfie.
     */
    public function checkOut(Employee $employee, array $context): AttendanceRecord
    {
        $setting = AttendanceSetting::current();
        $timezone = $this->resolveTimezone($setting);

        $record = AttendanceRecord::query()
            ->forDate(now($timezone))
            ->where('employee_id', $employee->id)
            ->first();

        if ($record === null || ! $record->hasCheckedIn()) {
            $this->audit->failed('attendance.check_out', $employee->user, $employee, 'Belum check-in');

            throw AttendanceException::make(
                'Anda belum check-in hari ini.',
                'NOT_CHECKED_IN',
                409,
            );
        }

        if ($record->hasCheckedOut()) {
            $this->audit->failed('attendance.check_out', $employee->user, $employee, 'Sudah check-out');

            throw AttendanceException::make(
                'Anda sudah check-out hari ini.',
                'ALREADY_CHECKED_OUT',
                409,
                ['check_out_at' => $record->check_out_at?->toIso8601String()],
            );
        }

        $location = $record->location;

        if ($location === null) {
            throw AttendanceException::make('Lokasi absensi tidak ditemukan.', 'LOCATION_NOT_FOUND', 422);
        }

        $distance = $this->validations->assertWithinRadius($employee, $location, $context);

        $selfie = $this->resolveSelfie($employee, $setting, $context, 'check-out');

        $record->fill([
            'check_out_at' => now($timezone),
            'check_out_latitude' => $context['latitude'] ?? null,
            'check_out_longitude' => $context['longitude'] ?? null,
            'check_out_distance' => $distance,
            'check_out_accuracy' => $context['accuracy'] ?? null,
            'check_out_selfie' => $selfie,
        ]);

        $record->work_minutes = $record->calculateWorkMinutes();
        $record->save();

        $this->audit->success(
            'attendance.check_out',
            $employee->user,
            $employee,
            sprintf('Check-out dari %s (%.2f m)', $location->name, $distance),
        );

        return $record->load(['location', 'validation']);
    }

    /**
     * Paginated attendance history of a single employee.
     */
    public function history(Employee $employee, array $filters)
    {
        $timezone = $this->resolveTimezone(AttendanceSetting::current());
        $from = $filters['from'] ?? now($timezone)->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now($timezone)->toDateString();
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 30)));

        $query = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->between($from, $to)
            ->with('location')
            ->orderByDesc('attendance_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $records = $query->paginate($perPage);

        $summary = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->between($from, $to)
            ->selectRaw('status, COUNT(*) as total, SUM(work_minutes) as minutes')
            ->groupBy('status')
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'records' => $records,
        ];
    }

    /**
     * Locations this employee may check in at, with live distance when a fix is given.
     */
    public function availableLocations(Employee $employee, ?array $context = null): array
    {
        $assigned = $employee->locations()->where('attendance_locations.status', AttendanceLocation::STATUS_ACTIVE);

        $locations = $assigned->exists()
            ? $assigned->get()
            : AttendanceLocation::query()->where('status', AttendanceLocation::STATUS_ACTIVE)->get();

        return $locations->map(function (AttendanceLocation $location) use ($context) {
            $distance = null;

            if (isset($context['latitude'], $context['longitude'])) {
                $distance = Geo::distance(
                    (float) $context['latitude'],
                    (float) $context['longitude'],
                    $location->latitude,
                    $location->longitude,
                );
            }

            return [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'radius' => $location->radius,
                'distance' => $distance,
                'within_range' => $distance === null ? null : $distance <= $location->radius,
            ];
        })->all();
    }

    public function registerDevice(Employee $employee, array $data): EmployeeDevice
    {
        $existing = EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_token', $data['device_token'])
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'device_name' => $data['device_name'] ?? $existing->device_name,
                'platform' => $data['platform'] ?? $existing->platform,
                'app_version' => $data['app_version'] ?? $existing->app_version,
                'last_used_at' => now(),
            ])->save();

            $device = $existing;
        } else {
            $device = EmployeeDevice::create([
                'employee_id' => $employee->id,
                'device_token' => $data['device_token'],
                'device_name' => $data['device_name'] ?? null,
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'status' => 'active',
                'registered_at' => now(),
                'last_used_at' => now(),
            ]);
        }

        $this->audit->success('device.register', $employee->user, $employee, 'Perangkat didaftarkan: '.($data['platform'] ?? 'unknown'));

        return $device;
    }

    /**
     * Validate the selfie reference sent by the mobile app. A selfie is
     * mandatory when the setting requires it and may only reference a file
     * uploaded by the same employee.
     */
    private function resolveSelfie(Employee $employee, AttendanceSetting $setting, array $context, string $phase): ?string
    {
        $selfie = $context['selfie'] ?? null;

        if (is_string($selfie)) {
            $selfie = trim($selfie);
        }

        if ($setting->require_selfie && ($selfie === null || $selfie === '')) {
            throw AttendanceException::make(
                "Selfie wajib diunggah saat {$phase}.",
                'SELFIE_REQUIRED',
                422,
            );
        }

        if ($selfie !== null && $selfie !== '' && ! SelfieController::ownsPath($employee, $selfie)) {
            $this->audit->failed('attendance.selfie', $employee->user, $employee, 'Referensi selfie bukan milik karyawan ini');

            throw AttendanceException::make(
                'Referensi selfie tidak valid. Silakan unggah ulang foto.',
                'INVALID_SELFIE',
                422,
            );
        }

        return $selfie === '' ? null : $selfie;
    }

    private function requireTicket(array $context): string
    {
        $ticket = $context['ticket'] ?? null;

        if (! is_string($ticket) || trim($ticket) === '') {
            throw AttendanceException::make(
                'Ticket validasi wajib diisi. Scan QR lokasi terlebih dahulu.',
                'TICKET_REQUIRED',
                422,
            );
        }

        return trim($ticket);
    }

    /**
     * Single timezone used for the attendance date, the stored timestamps and
     * the work hour thresholds. AppServiceProvider applies the same value to
     * config('app.timezone') at boot, so this stays correct even when the
     * settings row is created or edited during the request.
     */
    private function resolveTimezone(AttendanceSetting $setting): string
    {
        $tz = trim((string) $setting->timezone);

        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    private function assertWorkingHours(Employee $employee, Carbon $date, AttendanceSetting $setting): void
    {
        if (! $this->isWorkday($employee, $date)) {
            throw AttendanceException::make(
                'Hari ini bukan hari kerja Anda.',
                'NOT_A_WORKDAY',
                422,
            );
        }

        $schedule = $this->scheduleFor($employee, $date);

        if ($schedule !== null && $schedule['start'] !== null) {
            $tz = $this->resolveTimezone($setting);
            $now = now($tz);
            $startAt = Carbon::parse($date->toDateString().' '.$schedule['start'], $tz);

            if ($now->lt($startAt->copy()->subMinutes($setting->tolerance_minutes))) {
                throw AttendanceException::make(
                    'Check-in hanya dapat dilakukan mulai pukul '.$schedule['start'].'.',
                    'TOO_EARLY',
                    422,
                    ['start' => $schedule['start']],
                );
            }
        }
    }

    private function resolveStatus(Carbon $date, AttendanceSetting $setting): string
    {
        $tz = $this->resolveTimezone($setting);
        $now = now($tz);
        $workStart = substr((string) $setting->work_start, 0, 5);
        $lateThreshold = Carbon::parse($date->toDateString().' '.$workStart, $tz)
            ->addMinutes($setting->tolerance_minutes);

        return $now->gt($lateThreshold)
            ? AttendanceRecord::STATUS_TERLAMBAT
            : AttendanceRecord::STATUS_HADIR;
    }

    private function canCheckIn(Employee $employee, ?AttendanceRecord $record, Carbon $date): bool
    {
        if (! $employee->isActive()) {
            return false;
        }

        if ($record !== null && $record->hasCheckedIn()) {
            return false;
        }

        return $this->isWorkday($employee, $date);
    }

    private function stateOf(?AttendanceRecord $record): string
    {
        return match (true) {
            $record === null => 'belum_absen',
            $record->isComplete() => 'selesai',
            $record->hasCheckedIn() => 'sudah_masuk',
            default => 'belum_absen',
        };
    }

    private function isWorkday(Employee $employee, Carbon $date): bool
    {
        if (Holiday::isHoliday($date)) {
            return false;
        }

        $dayOfWeek = (int) $date->format('N');

        $personal = AttendanceSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        $schedule = $personal ?? AttendanceSchedule::query()
            ->whereNull('employee_id')
            ->where('day_of_week', $dayOfWeek)
            ->first();

        return $schedule === null ? true : (bool) $schedule->is_workday;
    }

    private function scheduleFor(Employee $employee, Carbon $date): ?array
    {
        $dayOfWeek = (int) $date->format('N');

        $schedule = AttendanceSchedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where(fn ($query) => $query
                ->where('employee_id', $employee->id)
                ->orWhereNull('employee_id'))
            ->orderByRaw('employee_id IS NULL')
            ->first();

        if ($schedule === null) {
            return null;
        }

        return [
            'is_workday' => (bool) $schedule->is_workday,
            'start' => $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : null,
            'end' => $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : null,
            'personal' => $schedule->employee_id !== null,
        ];
    }
}
