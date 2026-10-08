<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeDevice;
use App\Models\LocationValidation;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationValidationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Publish a single-use ticket after the QR code of a location was scanned.
     */
    public function issue(
        Employee $employee,
        string $scannedCode,
        array $context,
        ?Request $request = null,
    ): LocationValidation {
        $request ??= request();
        $setting = AttendanceSetting::current();
        $timezone = $this->resolveTimezone($setting);
        $token = $this->extractToken($scannedCode);

        $location = AttendanceLocation::query()
            ->where('public_token', $token)
            ->first();

        if ($location === null) {
            $this->audit->failed('qr.validate', $employee->user, $employee, 'Token QR tidak dikenal');

            throw AttendanceException::make('QR tidak valid atau sudah tidak berlaku.', 'INVALID_QR', 422);
        }

        if (! $location->isActive()) {
            $this->audit->failed('qr.validate', $employee->user, $employee, "Lokasi {$location->name} nonaktif");

            throw AttendanceException::make('Lokasi ini sedang tidak aktif.', 'LOCATION_INACTIVE', 422);
        }

        $this->assertEmployeeAllowedAt($employee, $location);

        $issuedToday = LocationValidation::query()
            ->where('employee_id', $employee->id)
            ->whereDate('issued_at', now($timezone))
            ->count();

        if ($issuedToday >= $setting->max_ticket_per_day) {
            $this->audit->failed('qr.validate', $employee->user, $employee, 'Kuota ticket harian habis');

            throw AttendanceException::make(
                'Jumlah permintaan validasi hari ini sudah habis.',
                'TICKET_QUOTA_EXCEEDED',
                429,
            );
        }

        $accuracy = isset($context['accuracy']) ? (float) $context['accuracy'] : null;

        if ($accuracy !== null && $accuracy > $setting->max_accuracy_meter) {
            $this->audit->failed('qr.validate', $employee->user, $employee, 'Akurasi GPS terlalu rendah');

            throw AttendanceException::make(
                'Sinyal GPS belum cukup stabil. Coba di area terbuka.',
                'GPS_INACCURATE',
                422,
                ['accuracy' => $accuracy, 'max_accuracy' => $setting->max_accuracy_meter],
            );
        }

        $validation = LocationValidation::create([
            'employee_id' => $employee->id,
            'location_id' => $location->id,
            'employee_device_id' => $this->resolveDeviceId($employee, $context),
            'ticket' => (string) Str::uuid(),
            'status' => LocationValidation::STATUS_ISSUED,
            'issued_at' => now($timezone),
            'expires_at' => now($timezone)->addSeconds($setting->ticket_ttl_seconds),
            'latitude' => $context['latitude'] ?? null,
            'longitude' => $context['longitude'] ?? null,
            'accuracy' => $accuracy,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        $this->audit->success(
            'qr.validate',
            $employee->user,
            $employee,
            "Ticket diterbitkan untuk lokasi {$location->name}",
        );

        return $validation->load('location');
    }

    /**
     * Consume a ticket exactly once. Returns the consumed validation.
     */
    public function consume(
        string $ticket,
        Employee $employee,
        ?AttendanceLocation $expectedLocation = null,
    ): LocationValidation {
        $validation = LocationValidation::query()
            ->where('ticket', $ticket)
            ->where('employee_id', $employee->id)
            ->first();

        if ($validation === null) {
            $this->audit->failed('attendance.check_in', $employee->user, $employee, 'Ticket tidak ditemukan');

            throw AttendanceException::make('Ticket validasi tidak ditemukan.', 'TICKET_NOT_FOUND', 422);
        }

        if ($expectedLocation !== null && $validation->location_id !== $expectedLocation->id) {
            $this->audit->failed('attendance.check_in', $employee->user, $employee, 'Ticket lokasi tidak cocok');

            throw AttendanceException::make(
                'Ticket validasi tidak sesuai dengan lokasi absensi.',
                'TICKET_LOCATION_MISMATCH',
                422,
            );
        }

        if ($validation->status === LocationValidation::STATUS_CONSUMED) {
            throw AttendanceException::make(
                'Ticket sudah pernah dipakai.',
                'TICKET_ALREADY_USED',
                422,
            );
        }

        if ($validation->isExpired()) {
            $validation->forceFill(['status' => LocationValidation::STATUS_EXPIRED])->save();

            $this->audit->failed('attendance.check_in', $employee->user, $employee, 'Ticket kedaluwarsa');

            throw AttendanceException::make('Ticket sudah kedaluwarsa, silakan scan ulang.', 'TICKET_EXPIRED', 422);
        }

        $consumed = LocationValidation::query()
            ->whereKey($validation->id)
            ->where('status', LocationValidation::STATUS_ISSUED)
            ->update([
                'status' => LocationValidation::STATUS_CONSUMED,
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($consumed === 0) {
            throw AttendanceException::make('Ticket sudah pernah dipakai.', 'TICKET_ALREADY_USED', 422);
        }

        return $validation->refresh();
    }

    /**
     * Distance check used by check-in and check-out.
     */
    public function assertWithinRadius(
        Employee $employee,
        AttendanceLocation $location,
        array $context,
    ): float {
        if (! isset($context['latitude'], $context['longitude'])) {
            $this->audit->failed('attendance.validate_location', $employee->user, $employee, 'Koordinat tidak dikirim');

            throw AttendanceException::make(
                'Lokasi GPS belum dapat dibaca.',
                'GPS_UNAVAILABLE',
                422,
            );
        }

        $distance = Geo::distance(
            (float) $context['latitude'],
            (float) $context['longitude'],
            $location->latitude,
            $location->longitude,
        );

        if ($distance > $location->radius) {
            $this->audit->failed(
                'attendance.validate_location',
                $employee->user,
                $employee,
                sprintf('Di luar radius %.2f m dari %s', $distance, $location->name),
            );

            throw AttendanceException::make(
                sprintf('Anda berada %.0f m dari %s. Maksimum %d m.', $distance, $location->name, $location->radius),
                'OUT_OF_RANGE',
                422,
                ['distance' => $distance, 'radius' => $location->radius],
            );
        }

        return $distance;
    }

    public function extractToken(string $scanned): string
    {
        $scanned = trim($scanned);

        if (str_starts_with($scanned, 'ABSENSI:')) {
            $scanned = substr($scanned, strlen('ABSENSI:'));
        }

        return trim($scanned);
    }

    /**
     * Kept in sync with AppServiceProvider so ticket timestamps and the daily
     * quota boundary follow the same zone as the attendance date.
     */
    private function resolveTimezone(AttendanceSetting $setting): string
    {
        $tz = trim((string) $setting->timezone);

        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    private function assertEmployeeAllowedAt(Employee $employee, AttendanceLocation $location): void
    {
        $assigned = $employee->locations()->pluck('attendance_locations.id');

        if ($assigned->isNotEmpty() && ! $assigned->contains($location->id)) {
            $this->audit->failed('qr.validate', $employee->user, $employee, "Lokasi {$location->name} tidak diizinkan");

            throw AttendanceException::make(
                'Anda tidak terdaftar pada lokasi ini.',
                'LOCATION_NOT_ASSIGNED',
                403,
            );
        }
    }

    private function resolveDeviceId(Employee $employee, array $context): ?int
    {
        $deviceToken = $context['device_id'] ?? null;

        if (! is_string($deviceToken) || $deviceToken === '') {
            return null;
        }

        return EmployeeDevice::query()
            ->where('employee_id', $employee->id)
            ->where('device_token', $deviceToken)
            ->value('id');
    }
}
