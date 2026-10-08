# Absensi Karyawan

Backend Laravel untuk sistem absensi karyawan berbasis QR, GPS, dan selfie.
Dikonsumsi oleh aplikasi Flutter.

## Stack

| Komponen | Versi |
| --- | --- |
| PHP | 8.4 |
| Laravel | 13.x |
| Database | MariaDB 10.4 / MySQL 8 |
| Auth | Laravel Sanctum (stateless bearer token) |
| Panel admin | Blade, tanpa build step |

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Kredensial demo:

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@absensi.test` | `password123` |
| Karyawan | `budi@absensi.test` | `password123` |
| Karyawan | `siti@absensi.test` | `password123` |

## Endpoint

Semua endpoint berada di bawah `/api/v1` dan wajib `Authorization: Bearer <token>`,
kecuali login.

| Method | Path | Keterangan |
| --- | --- | --- |
| POST | `/auth/login` | Terbatasi 5 permintaan/menit per email+IP |
| GET | `/me` | Profil user aktif |
| POST | `/auth/logout` | Mencabut token saat ini |
| GET | `/locations` | Lokasi yang boleh diakses employee |
| POST | `/qr/validate` | Tukar payload QR menjadi ticket sekali pakai |
| GET | `/attendance/today` | Status hari ini, lokasi terdekat, jadwal |
| POST | `/attendance/selfie` | Upload selfie ke disk privat (maks 4 MB) |
| POST | `/attendance/check-in` | Consume ticket + validasi radius + selfie |
| POST | `/attendance/check-out` | Validasi radius, selfie bila diwajibkan |
| GET | `/attendance/history` | Rekap rentang tanggal |

Kode error memakai bentuk `{ message, code, context }` dengan HTTP status yang sesuai.

## Panel Admin

Halaman: dashboard, karyawan, lokasi (termasuk QR), rekap absensi, jadwal kerja,
hari libur, laporan (export CSV), pengaturan, dan audit log.

Bukti selfie dapat dibuka dari detail absensi. Gambar dibaca langsung dari disk
privat, tidak pernah diekspos lewat URL publik, dan setiap pembukaan tercatat
di audit log sebagai `attendance.selfie.view`.

## Retensi Selfie

Selfie adalah data biometrik dan tidak disimpan selamanya.

```bash
php artisan absensi:prune-selfies --dry-run          # lihat dulu
php artisan absensi:prune-selfies                    # eksekusi
php artisan absensi:prune-selfies --keep-days=0      # nonaktifkan expiry
```

Dua aturan berjalan bersamaan:

- **Orphan** — berkas yang diunggah tapi tidak pernah terikat ke record absensi
  (check-in ditolak, employee dihapus) dihapus setelah
  `SELFIES_ORPHAN_GRACE_MINUTES` menit, default 60.
- **Expiry** — berkas yang sudah terikat ke record dihapus setelah
  `SELFIES_KEEP_DAYS` hari sejak tanggal absensi, default 30. Perbandingan
  memakai tanggal absensi, bukan waktu upload, sehingga unggahan ulang tidak
  memperpanjang masa retensi record lama.

## Backup Database

```bash
php artisan absensi:backup                          # dump + rotasi
php artisan absensi:backup --prune-only             # rotasi saja
php artisan absensi:backup --keep=30                # override jumlah salinan
```

Backup disimpan terkompresi (gzip) di `storage/app/private/backups` dengan nama
`absensi-YYYYMMDD-HHMMSS.sql.gz`. Secara default memakai `mysqldump`; bila
binary tidak ditemukan, command otomatis memakai dump SQL internal dan
memberi peringatan bahwa file tersebut hanya berisi data, bukan skema.

Restore:

```bash
gunzip -c backups/absensi-20260928-004655.sql.gz | mysql -uroot -p absensi
```

## Scheduler

`routes/console.php` mendaftarkan dua tugas harian:

| Waktu | Tugas |
| --- | --- |
| 02:10 | `absensi:prune-selfies` |
| 02:40 | `absensi:backup` |

Scheduler harus dipanggil berulang, karena Laravel hanya mengeksekusi tugas
yang sudah jatuh waktunya.

**Windows** — sudah dikonfigurasi lewat Task Scheduler:

```
Task name : AbsensiLaravelScheduler
Interval  : setiap 5 menit
Wrapper   : scripts\scheduler.bat
Log       : storage\logs\scheduler.log
```

Periksa dan jalankan manual:

```powershell
Get-ScheduledTaskInfo -TaskName AbsensiLaravelScheduler
Start-ScheduledTask -TaskName AbsensiLaravelScheduler
```

Hapus bila tidak diperlukan:

```powershell
Unregister-ScheduledTask -TaskName AbsensiLaravelScheduler -Confirm:$false
```

**Linux / macOS** — satu entri cron:

```cron
*/5 * * * * cd /path/ke/absensi_api && php artisan schedule:run >> /dev/null 2>&1
```

Untuk development, `php artisan schedule:work` lebih nyaman karena langsung
menjalankan tugas yang jatuh waktu tanpa menunggu tick berikutnya.

## Konfigurasi

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `SELFIES_ORPHAN_GRACE_MINUTES` | `60` | Tenggat orphan, `0` menonaktifkan |
| `SELFIES_KEEP_DAYS` | `30` | Masa retensi record, `0` menonaktifkan |
| `BACKUP_DISK` | `local` | Disk tujuan backup |
| `BACKUP_PATH` | `backups` | Direktori di dalam disk |
| `BACKUP_KEEP` | `14` | Jumlah salinan yang disimpan |
| `BACKUP_MYSQLDUMP` | auto-detect | Path absolut ke `mysqldump` |

Aturan absensi yang bisa diubah admin (jadwal kerja, radius, toleransi, TTL
ticket, kuota harian, kewajiban selfie) disimpan di tabel `attendance_settings`.

## Testing

```bash
php artisan test
vendor/bin/pint --preset laravel
```

Test suite memakai SQLite in-memory, jadi database MySQL pengembangan tidak
tersentuh.
