<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceLocation;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\LocationValidation;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrValidationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Employee
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->for($user)->create();
        Sanctum::actingAs($user);

        return $employee;
    }

    public function test_scanning_a_valid_qr_publishes_a_single_use_ticket(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $response = $this->postJson('/api/v1/qr/validate', [
            'code' => $location->qrPayload(),
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.single_use', true)
            ->assertJsonPath('data.status', LocationValidation::STATUS_ISSUED)
            ->assertJsonPath('data.location.id', $location->id)
            ->assertJsonStructure(['data' => ['ticket', 'expires_at', 'expires_in_seconds', 'location' => ['id', 'name', 'radius']]]);

        $this->assertDatabaseCount('location_validations', 1);
        $this->assertGreaterThan(now(), LocationValidation::first()->expires_at);
    }

    public function test_ticket_expires_after_configured_ttl(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        AttendanceSetting::current()->update(['ticket_ttl_seconds' => 30]);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        $this->assertEqualsWithDelta(
            now()->addSeconds(30)->timestamp,
            LocationValidation::first()->expires_at->timestamp,
            3,
        );
    }

    public function test_unknown_qr_token_is_rejected(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/qr/validate', ['code' => 'ABSENSI:loc_tidakDikenal'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_QR');

        $this->assertDatabaseCount('location_validations', 0);
    }

    public function test_inactive_location_is_rejected(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->inactive()->create();
        $employee->locations()->attach($location->id);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'LOCATION_INACTIVE');
    }

    public function test_employee_cannot_validate_a_location_they_are_not_assigned_to(): void
    {
        $employee = $this->signIn();
        $allowed = AttendanceLocation::factory()->create();
        $other = AttendanceLocation::factory()->create();
        $employee->locations()->attach($allowed->id);

        $this->postJson('/api/v1/qr/validate', ['code' => $other->qrPayload()])
            ->assertForbidden()
            ->assertJsonPath('code', 'LOCATION_NOT_ASSIGNED');
    }

    public function test_low_gps_accuracy_is_rejected(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id);

        AttendanceSetting::current()->update(['max_accuracy_meter' => 30]);

        $this->postJson('/api/v1/qr/validate', [
            'code' => $location->qrPayload(),
            'accuracy' => 120,
        ])->assertStatus(422)->assertJsonPath('code', 'GPS_INACCURATE');
    }

    public function test_daily_ticket_quota_is_enforced(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id);

        AttendanceSetting::current()->update(['max_ticket_per_day' => 2]);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();
        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertCreated();

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TICKET_QUOTA_EXCEEDED');
    }

    public function test_api_never_exposes_the_qr_secret(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id);

        $this->getJson('/api/v1/locations')->assertOk()->assertDontSee($location->public_token);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])
            ->assertCreated()
            ->assertDontSee($location->public_token);
    }

    public function test_bare_token_without_prefix_is_accepted(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id);

        $this->postJson('/api/v1/qr/validate', ['code' => $location->public_token])->assertCreated();
    }

    public function test_qr_validation_requires_authentication(): void
    {
        $location = AttendanceLocation::factory()->create();

        $this->postJson('/api/v1/qr/validate', ['code' => $location->qrPayload()])->assertUnauthorized();
    }

    public function test_locations_endpoint_reports_live_distance(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->radius(100)->create();
        $employee->locations()->attach($location->id);

        [$nearLat, $nearLng] = Geo::offset($location->latitude, $location->longitude, 0, 30);

        $this->getJson('/api/v1/locations?latitude='.$nearLat.'&longitude='.$nearLng)
            ->assertOk()
            ->assertJsonPath('data.0.within_range', true);

        [$farLat, $farLng] = Geo::offset($location->latitude, $location->longitude, 0, 900);

        $this->getJson('/api/v1/locations?latitude='.$farLat.'&longitude='.$farLng)
            ->assertOk()
            ->assertJsonPath('data.0.within_range', false);
    }

    public function test_admin_cannot_use_employee_attendance_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/qr/validate', ['code' => 'ABSENSI:apaSaja'])->assertForbidden();
        $this->getJson('/api/v1/attendance/today')->assertForbidden();
    }

    public function test_user_without_employee_profile_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/attendance/today')
            ->assertForbidden()
            ->assertJsonPath('code', 'EMPLOYEE_PROFILE_MISSING');
    }

    public function test_location_qr_token_survives_location_edits(): void
    {
        $employee = $this->signIn();
        $location = AttendanceLocation::factory()->create();
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $token = $location->public_token;

        $location->forceFill([
            'name' => 'Nama Baru',
            'address' => 'Alamat Baru',
            'latitude' => -1.234567,
            'longitude' => 100.987654,
            'radius' => AttendanceLocation::MAX_RADIUS_METERS,
        ])->save();

        $location->refresh();

        $this->assertSame($token, $location->public_token);
        $this->assertNull($location->qr_rotated_at);
        $this->assertSame($token, AttendanceLocation::findOrFail($location->id)->public_token);

        $this->postJson('/api/v1/qr/validate', [
            'code' => $location->qrPayload(),
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ])->assertCreated();
    }
}
