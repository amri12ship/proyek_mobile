<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'absensi:backup
                            {--prune-only : Buang backup lama tanpa membuat backup baru}
                            {--keep= : Override jumlah salinan yang disimpan}';

    protected $description = 'Buat backup database terkompresi di disk privat dan putar salinan lama';

    public function handle(): int
    {
        $disk = Storage::disk(config('absensi.backup.disk'));
        $path = trim((string) config('absensi.backup.path'), '/');
        $keep = $this->option('keep') !== null
            ? (int) $this->option('keep')
            : (int) config('absensi.backup.keep');

        if (! $this->option('prune-only')) {
            try {
                $file = $this->writeDump($disk, $path);

                $this->components->info(sprintf(
                    'Backup dibuat: %s (%s).',
                    $file,
                    $this->humanBytes((int) $disk->size($file)),
                ));
            } catch (Throwable $e) {
                $this->components->error('Backup gagal: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $pruned = $keep > 0 ? $this->prune($disk, $path, $keep) : 0;

        if ($pruned > 0) {
            $this->components->info("{$pruned} backup lama dihapus (menyisakan {$keep} salinan terbaru).");
        }

        return self::SUCCESS;
    }

    /**
     * @throws Throwable
     */
    private function writeDump(Filesystem $disk, string $path): string
    {
        $contents = $this->dumpContents();
        $name = 'absensi-'.Carbon::now()->format('Ymd-His').'.sql.gz';

        // Compressed in-process so the command does not depend on an
        // external gzip binary being present on the host.
        $encoded = gzencode($contents, 6);

        if ($encoded === false) {
            throw new RuntimeException('Gagal mengompresi dump.');
        }

        $target = trim($path, '/').'/'.$name;
        $disk->put($target, $encoded);

        return $target;
    }

    /**
     * @throws Throwable
     */
    private function dumpContents(): string
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) === 'mysql') {
            $binary = $this->mysqldumpBinary();

            if ($binary !== null) {
                return $this->dumpWithMysqldump($config, $binary);
            }
        }

        $this->components->warn('mysqldump tidak ditemukan, memakai dump SQL internal.');
        $this->components->warn('Dump ini hanya berisi data, bukan skema tabel. Jalankan migrate sebelum restore.');

        return $this->dumpAsSql();
    }

    private function mysqldumpBinary(): ?string
    {
        $configured = config('absensi.backup.mysqldump');

        if (is_string($configured) && $configured !== '') {
            return is_file($configured) ? $configured : null;
        }

        $candidates = array_filter([
            getenv('MYSQLDUMP_PATH') ?: null,
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file((string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws Throwable
     */
    private function dumpWithMysqldump(array $config, string $binary): string
    {
        $args = [
            $binary,
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? 'root'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            (string) $config['database'],
        ];

        if (($config['password'] ?? '') !== '') {
            $args[] = '--password='.(string) $config['password'];
        }

        $process = new Process($args);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || $process->getOutput() === '') {
            $reason = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException($reason !== '' ? $reason : 'mysqldump gagal tanpa pesan.');
        }

        return $process->getOutput();
    }

    /**
     * Portable fallback for hosts without the mysqldump binary. Emits
     * INSERT statements against the live schema, so it works on any driver
     * the application is configured with.
     *
     * @throws Throwable
     */
    private function dumpAsSql(): string
    {
        $connection = (string) config('database.default');
        $db = DB::connection($connection);
        $pdo = $db->getPdo();
        $database = (string) (config("database.connections.{$connection}.database") ?: 'absensi');

        $sql = "-- Absensi Karyawan backup (data only)\n";
        $sql .= '-- Database: '.$database."\n";
        $sql .= '-- Created: '.Carbon::now()->toIso8601String()."\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->baseTables($db, $pdo) as $table) {
            $sql .= self::insertsFor($table, $pdo)."\n";
        }

        return $sql."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    /**
     * @return array<int, string>
     */
    private function baseTables(mixed $db, PDO $pdo): array
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $tables = $driver === 'sqlite'
            ? $db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
            : $db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $names = [];

        foreach ($tables as $row) {
            $values = array_values((array) $row);
            $name = (string) end($values);

            if ($name !== '' && ! str_starts_with($name, 'sqlite_')) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @throws Throwable
     */
    private static function insertsFor(string $table, PDO $pdo): string
    {
        $statement = $pdo->query('SELECT * FROM `'.$table.'`');

        if ($statement === false) {
            return '';
        }

        $batches = [];
        $columns = null;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = implode(', ', array_map(static fn (string $c) => '`'.$c.'`', array_keys($row)));
            }

            $values = implode(', ', array_map(
                static fn ($value) => $value === null ? 'NULL' : self::quote((string) $value),
                array_values($row),
            ));

            $batches[] = '('.$columns.') VALUES ('.$values.')';
        }

        return $batches === []
            ? ''
            : 'INSERT INTO `'.$table.'` VALUES '.implode(', ', $batches).";\n";
    }

    private static function quote(string $value): string
    {
        if ($value === 'NULL' || is_numeric($value)) {
            return $value;
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function prune(Filesystem $disk, string $path, int $keep): int
    {
        $files = $disk->files(trim($path, '/'));

        if (count($files) <= $keep) {
            return 0;
        }

        usort($files, static fn (string $a, string $b) => $b <=> $a);

        $stale = array_slice($files, $keep);
        $disk->delete($stale);

        return count($stale);
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
