<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\LocationValidation;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private string $selfie = 'selfies/1/selfie.jpg';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set('Asia/Jakarta');

        parent::tearDown();
    }

    /**
     * Production boots AppServiceProvider with an attendance_settings row
     * already present, which is what arms applyApplicationTimezone().
     */
    private function rebootApplication(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }

    private function workingDay(): void
    {
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
            'ticket_ttl_seconds' => 120,
        ]);
    }

    /**
     * @return array{0: Employee, 1: AttendanceLocation, 2: array<string, mixed>}
     */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->for($user)->create();
        $location = AttendanceLocation::factory()->radius(200)->create();
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $this->selfie = 'selfies/'.$user->id.'/selfie.jpg';

        Sanctum::actingAs($user);

        [$latitude, $longitude] = Geo::offset($location->latitude, $location->longitude, 0, 25);

        return [$employee, $location, ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => 8]];
    }

    public function test_settings_timezone_overrides_the_env_value_at_boot(): void
    {
        AttendanceSetting::current()->update(['timezone' => 'Asia/Makassar']);

        $this->rebootApplication();

        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('Asia/Makassar', date_default_timezone_get());
        $this->assertSame('Asia/Makassar', now()->getTimezone()->getName());
    }

    public function test_boot_keeps_the_mutable_carbon_class_the_services_type_hint(): void
    {
        AttendanceSetting::current()->update(['timezone' => 'Asia/Jakarta']);

        $this->rebootApplication();

        $this->assertInstanceOf(Carbon::class, now());
        $this->assertNotInstanceOf(CarbonImmutable::class, now());
    }

    public function test_endpoints_still_respond_after_the_provider_override(): void
    {
        $this->workingDay();
        AttendanceSetting::current()->update(['timezone' => 'Asia/Jakarta']);

        $this->rebootApplication();

        Carbon::setTestNow(Carbon::parse('2026-09-28 08:05:00'));
        [, $location, $gps] = $this->scenario();

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Jakarta');

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        $this->postJson('/api/v1/attendance/check-in', $gps + [
            'ticket' => LocationValidation::latest('id')->first()->ticket,
            'selfie' => $this->selfie,
        ])->assertCreated();

        $this->assertSame('2026-09-28 08:05:00', AttendanceRecord::first()->getRawOriginal('check_in_at'));
    }

    public function test_reported_timezone_and_server_offset_never_disagree(): void
    {
        $this->workingDay();
        AttendanceSetting::current()->update(['timezone' => 'Asia/Makassar']);

        $this->rebootApplication();

        Carbon::setTestNow(Carbon::parse('2026-09-28 09:00:00'));
        $this->scenario();

        $data = $this->getJson('/api/v1/attendance/today')->assertOk()->json('data');

        $this->assertSame('Asia/Makassar', $data['timezone']);
        $this->assertSame('2026-09-28T09:00:00+08:00', $data['server_time']);
        $this->assertSame('2026-09-28', $data['date']);
    }

    public function test_late_threshold_follows_the_settings_timezone(): void
    {
        $this->workingDay();
        AttendanceSetting::current()->update(['timezone' => 'Asia/Makassar', 'tolerance_minutes' => 15]);

        $this->rebootApplication();

        // 08:20 WITA equals 07:20 WIB. Judged against a WITA threshold of 08:15
        // this is late; a WIB threshold would still call it on time.
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:20:00'));
        [, $location, $gps] = $this->scenario();

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        $this->postJson('/api/v1/attendance/check-in', $gps + [
            'ticket' => LocationValidation::latest('id')->first()->ticket,
            'selfie' => $this->selfie,
        ])->assertCreated();

        $record = AttendanceRecord::first();

        $this->assertSame('terlambat', $record->status);
        $this->assertSame('2026-09-28', $record->attendance_date->toDateString());
        $this->assertSame('2026-09-28 08:20:00', $record->getRawOriginal('check_in_at'));
    }

    public function test_attendance_date_follows_the_settings_timezone_across_midnight(): void
    {
        $this->workingDay();
        AttendanceSetting::current()->update(['timezone' => 'Asia/Makassar']);

        $this->rebootApplication();

        // 00:30 WITA on the 29th is still 23:30 WIB on the 28th.
        Carbon::setTestNow(Carbon::parse('2026-09-29 00:30:00'));
        [, $location, $gps] = $this->scenario();

        $data = $this->getJson('/api/v1/attendance/today')->assertOk()->json('data');

        $this->assertSame('2026-09-29', $data['date']);
        $this->assertSame('2026-09-29T00:30:00+08:00', $data['server_time']);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        $this->postJson('/api/v1/attendance/check-in', $gps + [
            'ticket' => LocationValidation::latest('id')->first()->ticket,
            'selfie' => $this->selfie,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TOO_EARLY');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_ticket_ttl_uses_the_settings_timezone(): void
    {
        $this->workingDay();
        AttendanceSetting::current()->update(['timezone' => 'Asia/Makassar']);

        $this->rebootApplication();

        Carbon::setTestNow(Carbon::parse('2026-09-28 09:00:00'));
        [, $location] = $this->scenario();

        $data = $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])
            ->assertCreated()
            ->json('data');

        $validation = LocationValidation::latest('id')->first();

        $this->assertSame('2026-09-28 09:00:00', $validation->getRawOriginal('issued_at'));
        $this->assertSame('2026-09-28 09:02:00', $validation->getRawOriginal('expires_at'));
        $this->assertSame('2026-09-28T09:02:00+08:00', $data['expires_at']);
        $this->assertSame(120, $data['expires_in_seconds']);
        $this->assertFalse($validation->isExpired());

        Carbon::setTestNow(Carbon::parse('2026-09-28 09:02:01'));

        $this->assertTrue($validation->fresh()->isExpired());
    }

    public function test_unreadable_or_invalid_stored_timezone_falls_back_to_the_env_value(): void
    {
        AttendanceSetting::current()->update(['timezone' => 'WIB']);

        $this->rebootApplication();

        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_empty_settings_table_keeps_the_env_value(): void
    {
        $this->assertSame(0, AttendanceSetting::count());

        $this->rebootApplication();

        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_local_date_is_used_instead_of_the_utc_date(): void
    {
        $this->workingDay();
        $this->scenario();

        Carbon::setTestNow(Carbon::parse('2026-09-28 23:30:00'));

        $data = $this->getJson('/api/v1/attendance/today')->assertOk()->json('data');

        $this->assertSame('2026-09-28', $data['date']);
        $this->assertSame('2026-09-28T23:30:00+07:00', $data['server_time']);
    }
}
