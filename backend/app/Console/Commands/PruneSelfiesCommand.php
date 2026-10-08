<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PruneSelfiesCommand extends Command
{
    protected $signature = 'absensi:prune-selfies
                            {--dry-run : Tampilkan file yang akan dihapus tanpa menghapusnya}
                            {--keep-days= : Override masa retensi record, 0 berarti nonaktif}
                            {--orphan-grace= : Override tenggat orphan dalam menit, 0 berarti nonaktif}';

    protected $description = 'Hapus selfie orphan yang sudah melewati tenggat dan selfie yang melewati masa retensi';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $path = config('absensi.selfie_retention.path');
        $grace = $this->option('orphan-grace') !== null
            ? (int) $this->option('orphan-grace')
            : (int) config('absensi.selfie_retention.orphan_grace_minutes');
        $keepDays = $this->option('keep-days') !== null
            ? (int) $this->option('keep-days')
            : (int) config('absensi.selfie_retention.keep_days');

        if (! $disk->exists($path)) {
            $this->components->info('Tidak ada direktori selfie, tidak ada yang diproses.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $orphans = $this->orphanPaths($disk, $path, $grace);
        $expired = $keepDays > 0 ? $this->expiredPaths($disk, $keepDays) : collect();
        $cleared = 0;

        $this->table(
            ['Kategori', 'Jumlah'],
            [
                ["Orphan (> {$grace} menit)", $orphans->count()],
                ["Melewati retensi ({$keepDays} hari)", $expired->count()],
            ],
        );

        $delete = $orphans->union($expired)->unique()->values();
        $bytes = 0;

        foreach ($delete as $file) {
            $bytes += (int) $disk->size($file);

            if (! $dryRun && $disk->delete($file)) {
                $this->forgetReferences($file);
                $cleared++;
            }
        }

        $verb = $dryRun ? 'akan dihapus' : 'dihapus';
        $this->components->info(sprintf(
            '%d selfie %s (%s dibebaskan).',
            $delete->count(),
            $verb,
            $this->humanBytes($bytes),
        ));

        if ($cleared > 0) {
            $this->components->info("{$cleared} rujukan pada attendance_records disetel menjadi null.");
        }

        if ($dryRun && $delete->isNotEmpty()) {
            $this->line('Contoh:');
            $this->line($delete->take(10)->implode(PHP_EOL));
        }

        return self::SUCCESS;
    }

    /**
     * Selfie yang tidak pernah terikat ke record absensi: unggahan yang
     * gagal, check-in ditolak, atau employee dihapus.
     *
     * @return Collection<int, string>
     */
    private function orphanPaths(Filesystem $disk, string $path, int $graceMinutes): Collection
    {
        if ($graceMinutes <= 0) {
            return collect();
        }

        $cutoff = Carbon::now()->subMinutes($graceMinutes);
        $referenced = $this->referencedPaths();
        $orphanFiles = [];

        foreach ($this->files($disk, $path) as $file) {
            if ($referenced->contains($file) || $disk->lastModified($file) > $cutoff->getTimestamp()) {
                continue;
            }

            $orphanFiles[] = $file;
        }

        return collect($orphanFiles);
    }

    /**
     * Selfie yang sudah terikat ke record lama. Compared against the
     * attendance date, not the file mtime, so re-uploads cannot extend
     * the retention window of an old attendance.
     *
     * @return Collection<int, string>
     */
    private function expiredPaths(Filesystem $disk, int $keepDays): Collection
    {
        $cutoff = Carbon::today()->subDays($keepDays);
        $expired = [];

        AttendanceRecord::query()
            ->where('attendance_date', '<', $cutoff->toDateString())
            ->select(['check_in_selfie', 'check_out_selfie'])
            ->chunk(500, function (Collection $records) use ($disk, &$expired): void {
                foreach ($records as $record) {
                    foreach ([$record->check_in_selfie, $record->check_out_selfie] as $file) {
                        if (is_string($file) && $file !== '' && $disk->exists($file)) {
                            $expired[] = $file;
                        }
                    }
                }
            });

        return collect($expired)->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function referencedPaths(): Collection
    {
        $referenced = collect();

        AttendanceRecord::query()
            ->select(['check_in_selfie', 'check_out_selfie'])
            ->chunk(500, function (Collection $records) use ($referenced): void {
                foreach ($records as $record) {
                    $referenced->push($record->check_in_selfie, $record->check_out_selfie);
                }
            });

        return $referenced->filter(fn ($file) => is_string($file) && $file !== '')->unique()->values();
    }

    /**
     * Null out the column so the admin panel shows "Tidak ada" instead of a
     * broken image pointing at a file that no longer exists.
     */
    private function forgetReferences(string $file): void
    {
        foreach (['check_in_selfie', 'check_out_selfie'] as $column) {
            AttendanceRecord::query()->where($column, $file)->update([$column => null]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function files(Filesystem $disk, string $path): array
    {
        $all = $disk->allFiles($path);

        return array_values(array_filter(
            $all,
            fn (string $file) => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true),
        ));
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;

        for ($i = 0; $value >= 1024 && $i < count($units) - 1; $i++) {
            $value /= 1024;
        }

        return round($value, 2).' '.$units[$i];
    }
}
