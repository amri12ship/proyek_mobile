<?php

namespace Tests\Feature\Web;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_login_page_is_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk', false);
    }

    public function test_guest_is_redirected_from_admin_pages(): void
    {
        foreach (['/admin', '/admin/employees', '/admin/locations', '/admin/absensi', '/admin/laporan'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_employee_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/admin', '/admin/employees', '/admin/locations', '/admin/absensi', '/admin/jadwal', '/admin/libur', '/admin/laporan', '/admin/pengaturan', '/admin/audit'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_employee_dashboard_and_profile_are_reachable(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk();
        $this->get('/profil')->assertOk();
    }

    public function test_every_admin_page_renders_for_admin(): void
    {
        $this->admin();
        AttendanceSetting::current();
        AttendanceLocation::factory()->create();

        $selfie = Employee::factory()->create();
        $withSelfie = AttendanceRecord::create([
            'employee_id' => $selfie->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->setTime(8, 0),
            'check_in_selfie' => 'selfies/'.$selfie->user_id.'/abc.jpg',
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->get('/admin/absensi/'.$withSelfie->id)
            ->assertOk()
            ->assertSee('Bukti Selfie', false)
            ->assertSee(route('admin.attendance.selfie', [$withSelfie, 'check-in']), false);

        $pages = [
            '/admin' => 'Dashboard Admin',
            '/admin/employees' => 'Data Karyawan',
            '/admin/employees/create' => 'Tambah Karyawan',
            '/admin/locations' => 'Lokasi Absensi',
            '/admin/locations/create' => 'Tambah Lokasi',
            '/admin/absensi' => 'Rekap Absensi Harian',
            '/admin/jadwal' => 'Jadwal Kerja',
            '/admin/libur' => 'Hari Libur',
            '/admin/laporan' => 'Laporan Absensi',
            '/admin/pengaturan' => 'Pengaturan Absensi',
            '/admin/audit' => 'Audit Log',
        ];

        foreach ($pages as $url => $needle) {
            $this->get($url)->assertOk()->assertSee($needle, false);
        }
    }

    public function test_qr_page_reveals_the_secret_only_for_admins(): void
    {
        $admin = $this->admin();
        $location = AttendanceLocation::factory()->create();

        $this->get('/admin/locations/'.$location->id.'/qr')
            ->assertOk()
            ->assertSee($location->public_token, false);

        $this->get('/admin/locations/'.$location->id.'/qr/image')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->actingAs(User::factory()->create());
        $this->get('/admin/locations/'.$location->id.'/qr')->assertForbidden();
        $this->assertNotNull($admin);
    }

    public function test_admin_can_create_an_employee_with_location_access(): void
    {
        $this->admin();
        $location = AttendanceLocation::factory()->create();

        $this->post('/admin/employees', [
            'nik' => 'EMP777',
            'name' => 'Rina Wijaya',
            'email' => 'rina@absensi.test',
            'username' => 'rina',
            'password' => 'password123',
            'department_id' => null,
            'position_id' => null,
            'status' => 'active',
            'locations' => [$location->id],
            'primary_location' => $location->id,
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('users', ['email' => 'rina@absensi.test', 'role' => 'employee']);
        $this->assertDatabaseHas('employees', ['nik' => 'EMP777', 'name' => 'Rina Wijaya']);

        $employee = Employee::where('nik', 'EMP777')->firstOrFail();
        $this->assertTrue($employee->locations->contains($location));
    }

    public function test_admin_can_create_a_location_and_rotate_its_token(): void
    {
        $this->admin();

        $this->post('/admin/locations', [
            'name' => 'Kantor Cabang',
            'address' => 'Jl. Anggrek No. 5',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 150,
            'status' => 'active',
        ])->assertRedirect(route('admin.locations.index'));

        $location = AttendanceLocation::where('name', 'Kantor Cabang')->firstOrFail();
        $original = $location->public_token;

        $this->post('/admin/locations/'.$location->id.'/rotate-token')->assertRedirect();

        $location->refresh();
        $this->assertNotSame($original, $location->public_token);
        $this->assertNotNull($location->qr_rotated_at);
    }

    public function test_location_form_can_capture_coordinates_from_the_browser_gps(): void
    {
        $this->admin();

        $this->get('/admin/locations/create')
            ->assertOk()
            ->assertSee('Ambil Koordinat dari GPS')
            ->assertSee('getCurrentPosition', false);

        $this->get('/admin/locations/'.AttendanceLocation::factory()->create()->id.'/edit')
            ->assertOk()
            ->assertSee('getCurrentPosition', false);
    }

    public function test_admin_can_override_attendance_status(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->setTime(9, 0),
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->put('/admin/absensi/'.$record->id.'/status', [
            'status' => AttendanceRecord::STATUS_IZIN,
            'notes' => 'Surat izin dokter',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'status' => AttendanceRecord::STATUS_IZIN,
            'notes' => 'Surat izin dokter',
        ]);
    }

    public function test_admin_panel_renders_employee_user_without_a_link(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($employee->user);

        $this->get('/dashboard')->assertOk();
        $this->assertNotNull($employee);
    }

    public function test_admin_can_open_a_stored_selfie_but_an_employee_cannot(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();
        Storage::fake('local');
        Storage::disk('local')->put('selfies/'.$employee->user_id.'/abc.jpg', 'fake-jpeg-bytes');

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->setTime(8, 0),
            'check_in_selfie' => 'selfies/'.$employee->user_id.'/abc.jpg',
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->get(route('admin.attendance.selfie', [$record, 'check-in']))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private');

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.selfie.view']);

        $this->get(route('admin.attendance.selfie', [$record, 'check-out']))->assertNotFound();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.attendance.selfie', [$record, 'check-in']))->assertForbidden();
    }

    public function test_admin_can_manage_schedules_holidays_and_settings(): void
    {
        $this->admin();

        $this->post('/admin/jadwal', [
            'day_of_week' => 6,
            'is_workday' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_schedules', ['day_of_week' => 6, 'is_workday' => false]);

        $this->post('/admin/libur', [
            'date' => '2026-12-25',
            'name' => 'Hari Raya Natal',
        ])->assertRedirect();

        $this->assertTrue(Holiday::where('name', 'Hari Raya Natal')->exists());

        $this->put('/admin/pengaturan', [
            'work_start' => '07:30',
            'work_end' => '16:30',
            'tolerance_minutes' => 10,
            'radius_meter' => 150,
            'max_accuracy_meter' => 40,
            'ticket_ttl_seconds' => 90,
            'max_ticket_per_day' => 5,
            'require_selfie' => '1',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect();

        $setting = AttendanceSetting::current();
        $this->assertSame('07:30:00', $setting->work_start);
        $this->assertSame(90, $setting->ticket_ttl_seconds);
        $this->assertTrue($setting->require_selfie);
    }

    public function test_report_can_be_exported_as_csv(): void
    {
        $this->admin();
        $employee = Employee::factory()->create();

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'check_in_at' => now()->subDay()->setTime(8, 0),
            'check_out_at' => now()->subDay()->setTime(17, 0),
            'work_minutes' => 540,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $response = $this->get('/admin/laporan/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString($employee->nik, $response->streamedContent());
    }

    public function test_web_login_and_logout_work(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@absensi.test']);

        $this->post('/login', ['email' => 'admin@absensi.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_web_login_with_wrong_password_fails(): void
    {
        User::factory()->admin()->create(['email' => 'admin@absensi.test']);

        $this->post('/login', ['email' => 'admin@absensi.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_use_the_web_panel(): void
    {
        $this->actingAs(User::factory()->inactive()->create());

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_holiday_and_audit_pages_show_data(): void
    {
        $this->admin();
        Holiday::create(['date' => '2026-12-25', 'name' => 'Natal']);
        $location = AttendanceLocation::factory()->create(['name' => 'Kantor Pusat']);

        $this->get('/admin/libur')->assertOk()->assertSee('Natal', false);
        $this->get('/admin/locations')->assertOk()->assertSee('Kantor Pusat', false);
        $this->assertNotNull($location);
    }

    public function test_new_location_gets_one_qr_token_that_survives_admin_edits(): void
    {
        $this->admin();

        $this->post('/admin/locations', [
            'name' => 'Wilayah Operasional',
            'address' => 'Jl. Merdeka No. 1',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => AttendanceLocation::DEFAULT_RADIUS_METERS,
            'status' => 'active',
        ])->assertRedirect(route('admin.locations.index'));

        $location = AttendanceLocation::where('name', 'Wilayah Operasional')->firstOrFail();
        $token = $location->public_token;

        $this->assertNotEmpty($token);
        $this->assertNull($location->qr_rotated_at);

        $this->put('/admin/locations/'.$location->id, [
            'name' => 'Wilayah Operasional',
            'address' => 'Jl. Merdeka No. 99',
            'latitude' => -6.3,
            'longitude' => 106.9,
            'radius' => AttendanceLocation::MAX_RADIUS_METERS,
            'status' => 'active',
        ])->assertRedirect(route('admin.locations.index'));

        $location->refresh();

        $this->assertSame($token, $location->public_token);
        $this->assertSame('Jl. Merdeka No. 99', $location->address);
        $this->assertSame(AttendanceLocation::MAX_RADIUS_METERS, $location->radius);
        $this->assertNull($location->qr_rotated_at);
    }

    public function test_location_radius_is_capped_at_one_hundred_kilometers(): void
    {
        $this->admin();

        $this->post('/admin/locations', [
            'name' => 'Radius Kebesaran',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => AttendanceLocation::MAX_RADIUS_METERS + 1,
            'status' => 'active',
        ])->assertSessionHasErrors('radius');

        $this->assertDatabaseMissing('attendance_locations', ['name' => 'Radius Kebesaran']);
    }

    public function test_create_location_form_defaults_to_the_maximum_radius(): void
    {
        $this->admin();

        $this->get('/admin/locations/create')
            ->assertOk()
            ->assertSee('value="'.AttendanceLocation::DEFAULT_RADIUS_METERS.'"', false);
    }

    public function test_token_rotation_is_only_offered_from_the_qr_page(): void
    {
        $this->admin();
        $location = AttendanceLocation::factory()->create();

        $this->get('/admin/locations/'.$location->id.'/edit')
            ->assertOk()
            ->assertDontSee(route('admin.locations.rotate', $location), false);

        $this->get('/admin/locations/'.$location->id.'/qr')
            ->assertOk()
            ->assertSee(route('admin.locations.rotate', $location), false);
    }
}
