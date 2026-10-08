<?php

namespace Tests\Feature\Console;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneSelfiesCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SELFIE = 'selfies/9/aaaaaaaa.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'absensi.selfie_retention.path' => 'selfies',
            'absensi.selfie_retention.orphan_grace_minutes' => 60,
            'absensi.selfie_retention.keep_days' => 30,
        ]);
    }

    private function store(string $path, ?Carbon $modifiedAt = null): string
    {
        Storage::disk('local')->put($path, 'jpeg-bytes');

        if ($modifiedAt !== null) {
            touch(Storage::disk('local')->path($path), $modifiedAt->getTimestamp());
        }

        return $path;
    }

    public function test_it_removes_orphans_that_exceeded_the_grace_period(): void
    {
        $stale = $this->store('selfies/9/old.jpg', Carbon::now()->subHours(5));
        $fresh = $this->store('selfies/9/fresh.jpg', Carbon::now()->subMinutes(5));

        $this->artisan('absensi:prune-selfies')->assertSuccessful();

        Storage::disk('local')->assertMissing($stale);
        Storage::disk('local')->assertExists($fresh);
    }

    public function test_it_keeps_orphans_referenced_by_a_record(): void
    {
        $employee = Employee::factory()->create();
        $path = $this->store(self::SELFIE, Carbon::now()->subDays(3));

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now()->setTime(8, 0),
            'check_in_selfie' => $path,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->artisan('absensi:prune-selfies')->assertSuccessful();

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $record->fresh()->check_in_selfie);
    }

    public function test_deleting_a_file_clears_its_column(): void
    {
        $employee = Employee::factory()->create();
        $path = $this->store(self::SELFIE, Carbon::now()->subDays(90));

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today()->subDays(60)->toDateString(),
            'check_in_at' => Carbon::today()->subDays(60)->setTime(8, 0),
            'check_in_selfie' => $path,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->artisan('absensi:prune-selfies')->assertSuccessful();

        $this->assertNull($record->fresh()->check_in_selfie);
    }

    public function test_it_removes_selfies_beyond_the_retention_window(): void
    {
        $employee = Employee::factory()->create();
        $oldPath = $this->store(self::SELFIE, Carbon::now()->subDays(90));
        $recentPath = $this->store('selfies/9/recent.jpg', Carbon::now());

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today()->subDays(60)->toDateString(),
            'check_in_at' => Carbon::today()->subDays(60)->setTime(8, 0),
            'check_in_selfie' => $oldPath,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today()->subDays(3)->toDateString(),
            'check_in_at' => Carbon::today()->subDays(3)->setTime(8, 0),
            'check_out_selfie' => $recentPath,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->artisan('absensi:prune-selfies')->assertSuccessful();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($recentPath);
    }

    public function test_keep_days_zero_disables_expiry_but_not_orphan_cleanup(): void
    {
        $employee = Employee::factory()->create();
        $oldPath = $this->store(self::SELFIE, Carbon::now()->subDays(200));
        $orphan = $this->store('selfies/9/orphan.jpg', Carbon::now()->subDays(200));

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'attendance_date' => Carbon::today()->subDays(180)->toDateString(),
            'check_in_at' => Carbon::today()->subDays(180)->setTime(8, 0),
            'check_in_selfie' => $oldPath,
            'status' => AttendanceRecord::STATUS_HADIR,
        ]);

        $this->artisan('absensi:prune-selfies', ['--keep-days' => 0])->assertSuccessful();

        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertMissing($orphan);
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $stale = $this->store('selfies/9/old.jpg', Carbon::now()->subDays(5));

        $this->artisan('absensi:prune-selfies', ['--dry-run' => true])
            ->expectsOutputToContain('akan dihapus')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($stale);
    }

    public function test_it_ignores_non_image_files(): void
    {
        $notes = $this->store('selfies/9/readme.txt', Carbon::now()->subDays(5));

        $this->artisan('absensi:prune-selfies')->assertSuccessful();

        Storage::disk('local')->assertExists($notes);
    }

    public function test_it_succeeds_when_the_directory_is_missing(): void
    {
        $this->artisan('absensi:prune-selfies')
            ->expectsOutputToContain('Tidak ada direktori selfie')
            ->assertSuccessful();
    }
}
