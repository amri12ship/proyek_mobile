<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'absensi.backup.disk' => 'local',
            'absensi.backup.path' => 'backups',
            'absensi.backup.keep' => 3,
        ]);
    }

    public function test_it_writes_a_compressed_sql_dump(): void
    {
        $this->artisan('absensi:backup')->assertSuccessful();

        $files = Storage::disk('local')->files('backups');

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^backups\/absensi-\d{8}-\d{6}\.sql\.gz$/', $files[0]);

        $decoded = gzdecode(Storage::disk('local')->get($files[0]));

        $this->assertIsString($decoded);
        $this->assertStringContainsString('Absensi Karyawan', $decoded);
        $this->assertStringContainsString('INSERT INTO', $decoded);
    }

    public function test_the_dump_contains_actual_rows(): void
    {
        $this->artisan('absensi:backup')->assertSuccessful();

        $file = Storage::disk('local')->files('backups')[0];
        $decoded = (string) gzdecode(Storage::disk('local')->get($file));

        $this->assertStringContainsString('migrations', $decoded);
    }

    public function test_it_prunes_old_backups_beyond_the_keep_count(): void
    {
        foreach (['2026-01-01-010101', '2026-01-02-010101', '2026-01-03-010101', '2026-01-04-010101', '2026-01-05-010101'] as $stamp) {
            Storage::disk('local')->put("backups/absensi-{$stamp}.sql.gz", 'x');
        }

        $this->artisan('absensi:backup', ['--prune-only' => true])
            ->expectsOutputToContain('2 backup lama dihapus')
            ->assertSuccessful();

        $this->assertCount(3, Storage::disk('local')->files('backups'));
        $this->assertTrue(Storage::disk('local')->exists('backups/absensi-2026-01-05-010101.sql.gz'));
        $this->assertFalse(Storage::disk('local')->exists('backups/absensi-2026-01-01-010101.sql.gz'));
    }

    public function test_keep_option_overrides_config(): void
    {
        foreach (['2026-02-01-010101', '2026-02-02-010101', '2026-02-03-010101'] as $stamp) {
            Storage::disk('local')->put("backups/absensi-{$stamp}.sql.gz", 'x');
        }

        $this->artisan('absensi:backup', ['--prune-only' => true, '--keep' => 1])->assertSuccessful();

        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    public function test_prune_only_does_not_create_a_new_backup(): void
    {
        $this->artisan('absensi:backup', ['--prune-only' => true])->assertSuccessful();

        $this->assertCount(0, Storage::disk('local')->files('backups'));
    }

    public function test_a_new_dump_is_created_even_when_old_backups_exist(): void
    {
        Storage::disk('local')->put('backups/absensi-20260101-010101.sql.gz', 'x');

        Carbon::setTestNow(Carbon::create(2026, 9, 28, 2, 40));

        try {
            $this->artisan('absensi:backup')->assertSuccessful();

            $files = Storage::disk('local')->files('backups');

            $this->assertCount(2, $files);
            $this->assertTrue(Storage::disk('local')->exists('backups/absensi-20260928-024000.sql.gz'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_keep_zero_disables_rotation(): void
    {
        foreach (['2026-03-01-010101', '2026-03-02-010101', '2026-03-03-010101', '2026-03-04-010101'] as $stamp) {
            Storage::disk('local')->put("backups/absensi-{$stamp}.sql.gz", 'x');
        }

        $this->artisan('absensi:backup', ['--prune-only' => true, '--keep' => 0])->assertSuccessful();

        $this->assertCount(4, Storage::disk('local')->files('backups'));
    }
}
