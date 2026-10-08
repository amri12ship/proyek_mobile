<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LocationValidation;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private ?string $selfie = null;

    private function workingDay(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:45:00'));

        foreach (range(1, 7) as $day) {
            AttendanceSchedule::create([
                'employee_id' => null,
                'day_of_week' => $day,
                'is_workday' => true,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        AttendanceSetting::current()->update([
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'tolerance_minutes' => 15,
            'require_selfie' => true,
        ]);
    }

    /**
     * @return array{0: Employee, 1: AttendanceLocation}
     */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->for($user)->create();
        $location = AttendanceLocation::factory()->radius(200)->create();
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $this->selfie = 'selfies/'.$user->id.'/selfie.jpg';

        Sanctum::actingAs($user);

        return [$employee, $location];
    }

    /**
     * @return array<string, mixed>
     */
    private function insideLocation(AttendanceLocation $location): array
    {
        [$latitude, $longitude] = Geo::offset($location->latitude, $location->longitude, 0, 25);

        return ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 8];
    }

    private function issueTicket(AttendanceLocation $location): string
    {
        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        return LocationValidation::latest('id')->first()->ticket;
    }

    public function test_today_snapshot_reports_initial_state(): void
    {
        $this->workingDay();
        $this->scenario();

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', 'belum_absen')
            ->assertJsonPath('data.can_check_in', true)
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.is_workday', true)
            ->assertJsonPath('data.is_holiday', false)
            ->assertJsonPath('data.work_hours.start', '08:00')
            ->assertJsonPath('data.record', null)
            ->assertJsonStructure(['data' => ['locations' => [['id', 'name', 'radius', 'distance', 'within_range']]]]);
    }

    public function test_check_in_records_time_and_location(): void
    {
        $this->workingDay();
        [$employee, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', AttendanceRecord::STATUS_HADIR)
            ->assertJsonPath('data.has_selfie', true)
            ->assertJsonPath('data.check_in', '2026-09-28T07:45:00+07:00');

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id,
            'location_id' => $location->id,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->assertDatabaseHas('location_validations', [
            'ticket' => $ticket,
            'status' => LocationValidation::STATUS_CONSUMED,
        ]);
    }

    public function test_check_in_is_rejected_outside_the_radius(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        [$latitude, $longitude] = Geo::offset($location->latitude, $location->longitude, 0, 500);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'OUT_OF_RANGE');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_check_in_is_allowed_far_inside_the_maximum_radius(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $location->update(['radius' => AttendanceLocation::MAX_RADIUS_METERS]);
        $ticket = $this->issueTicket($location->refresh());

        [$latitude, $longitude] = Geo::offset($location->latitude, $location->longitude, 50000, 0);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'selfie' => $this->selfie,
        ])->assertCreated();

        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_check_in_is_rejected_beyond_the_maximum_radius(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $location->update(['radius' => AttendanceLocation::MAX_RADIUS_METERS]);
        $ticket = $this->issueTicket($location->refresh());

        [$latitude, $longitude] = Geo::offset($location->latitude, $location->longitude, AttendanceLocation::MAX_RADIUS_METERS + 5000, 0);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'OUT_OF_RANGE');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_check_in_after_tolerance_is_marked_late(): void
    {
        $this->workingDay();

        Carbon::setTestNow(Carbon::parse('2026-09-28 08:40:00'));

        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertCreated()->assertJsonPath('data.status', AttendanceRecord::STATUS_TERLAMBAT);
    }

    public function test_ticket_cannot_be_replayed(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $payload = ['ticket' => $ticket, ...$this->insideLocation($location), 'selfie' => $this->selfie];

        $this->postJson('/api/v1/attendance/check-in', $payload)->assertCreated();

        $this->postJson('/api/v1/attendance/check-in', $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_CHECKED_IN');

        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_consumed_ticket_cannot_be_reused_after_the_day_rolls_over(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-09-29 07:45:00'));

        $fresh = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $fresh,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertCreated();

        $this->assertDatabaseHas('location_validations', ['ticket' => $ticket, 'status' => LocationValidation::STATUS_CONSUMED]);
        $this->assertDatabaseCount('attendance_records', 2);
    }

    public function test_expired_ticket_is_rejected(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        Carbon::setTestNow(Carbon::parse('2026-09-28 07:50:00'));

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'TICKET_EXPIRED');

        $this->assertDatabaseHas('location_validations', ['ticket' => $ticket, 'status' => LocationValidation::STATUS_EXPIRED]);
    }

    public function test_ticket_from_another_employee_cannot_be_used(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $otherUser = User::factory()->create();
        $other = Employee::factory()->for($otherUser)->create();
        $other->locations()->attach($location->id);

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'TICKET_NOT_FOUND');
    }

    public function test_check_in_requires_a_ticket(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();

        $this->postJson('/api/v1/attendance/check-in', [
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonValidationErrors(['ticket']);
    }

    public function test_check_in_requires_a_selfie_when_configured(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
        ])->assertStatus(422)->assertJsonPath('code', 'SELFIE_REQUIRED');
    }

    public function test_check_in_blocked_on_a_holiday(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        Holiday::create(['date' => '2026-09-28', 'name' => 'Libur Uji']);
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'NOT_A_WORKDAY');
    }

    public function test_check_in_blocked_on_a_non_workday(): void
    {
        $this->workingDay();

        AttendanceSchedule::query()
            ->whereNull('employee_id')
            ->where('day_of_week', 6)
            ->update(['is_workday' => false]);

        Carbon::setTestNow(Carbon::parse('2026-10-03 07:45:00'));

        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'NOT_A_WORKDAY');
    }

    public function test_check_in_too_early_is_rejected(): void
    {
        $this->workingDay();

        Carbon::setTestNow(Carbon::parse('2026-09-28 06:30:00'));

        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertStatus(422)->assertJsonPath('code', 'TOO_EARLY');
    }

    public function test_check_in_rejects_a_selfie_owned_by_someone_else(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => 'selfies/999999/selfie.jpg',
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_SELFIE');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_inactive_employee_cannot_check_in(): void
    {
        $this->workingDay();
        [$employee, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $employee->update(['status' => Employee::STATUS_INACTIVE]);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertForbidden()->assertJsonPath('code', 'EMPLOYEE_INACTIVE');
    }

    public function test_check_out_completes_the_record(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-09-28 17:05:00'));

        $this->postJson('/api/v1/attendance/check-out', [
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertOk()->assertJsonPath('data.check_out', '2026-09-28T17:05:00+07:00');

        $this->assertDatabaseHas('attendance_records', [
            'check_out_at' => '2026-09-28 17:05:00',
            'work_minutes' => 560,
        ]);

        $this->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.can_check_out', false);
    }

    public function test_check_out_without_check_in_is_rejected(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();

        $this->postJson('/api/v1/attendance/check-out', $this->insideLocation($location) + ['selfie' => $this->selfie])
            ->assertStatus(409)
            ->assertJsonPath('code', 'NOT_CHECKED_IN');
    }

    public function test_check_out_twice_is_rejected(): void
    {
        $this->workingDay();
        [, $location] = $this->scenario();
        $ticket = $this->issueTicket($location);

        $this->postJson('/api/v1/attendance/check-in', [
            'ticket' => $ticket,
            ...$this->insideLocation($location),
            'selfie' => $this->selfie,
        ])->assertCreated();

        $this->postJson('/api/v1/attendance/check-out', $this->insideLocation($location) + ['selfie' => $this->selfie])->assertOk();
        $this->postJson('/api/v1/attendance/check-out', $this->insideLocation($location) + ['selfie' => $this->selfie])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_CHECKED_OUT');
    }

    public function test_history_returns_records_with_summary(): void
    {
        $this->workingDay();
        [$employee, $location] = $this->scenario();

        foreach (['2026-09-21', '2026-09-22', '2026-09-23'] as $date) {
            AttendanceRecord::create([
                'employee_id' => $employee->id,
                'location_id' => $location->id,
                'attendance_date' => $date,
                'check_in_at' => $date.' 07:55:00',
                'check_out_at' => $date.' 17:00:00',
                'work_minutes' => 545,
                'status' => AttendanceRecord::STATUS_HADIR,
            ]);
        }

        $this->getJson('/api/v1/attendance/history?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.from', '2026-09-01')
            ->assertJsonPath('data.0.date', '2026-09-23');
    }

    public function test_history_only_returns_the_owner_records(): void
    {
        $this->workingDay();
        [$employee, $location] = $this->scenario();

        $other = Employee::factory()->create();
        AttendanceRecord::create([
            'employee_id' => $other->id,
            'location_id' => $location->id,
            'attendance_date' => '2026-09-22',
            'check_in_at' => '2026-09-22 08:00:00',
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'location_id' => $location->id,
            'attendance_date' => '2026-09-22',
            'check_in_at' => '2026-09-22 08:00:00',
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->getJson('/api/v1/attendance/history')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_attendance_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/attendance/today')->assertUnauthorized();
        $this->postJson('/api/v1/attendance/check-in', [])->assertUnauthorized();
        $this->postJson('/api/v1/attendance/check-out', [])->assertUnauthorized();
        $this->getJson('/api/v1/attendance/history')->assertUnauthorized();
    }

    public function test_device_registration_is_idempotent(): void
    {
        $this->workingDay();
        [$employee] = $this->scenario();

        $this->postJson('/api/v1/device', [
            'device_token' => 'android-abc',
            'device_name' => 'Xiaomi Redmi',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ])->assertCreated();

        $this->postJson('/api/v1/device', [
            'device_token' => 'android-abc',
            'platform' => 'android',
            'app_version' => '1.0.1',
        ])->assertCreated();

        $this->assertDatabaseCount('employee_devices', 1);
        $this->getJson('/api/v1/device')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('employee_devices', ['employee_id' => $employee->id, 'app_version' => '1.0.1']);
    }
}
