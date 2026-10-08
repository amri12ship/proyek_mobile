<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retensi Selfie
    |--------------------------------------------------------------------------
    |
    | Selfie absensi adalah data biometrik dan tidak boleh disimpan selamanya.
    | Dua aturan Berlaku bersamaan:
    |
    |   orphan_grace_minutes  berkas yang diunggah tetapi tidak pernah
    |                        terikat ke record (mis. check-in ditolak)
    |                        dibersihkan setelah tenggat ini.
    |   keep_days            berkas yang sudah terikat ke record disimpan
    |                        selama N hari sejak check-in happened, lalu
    |                        dihapus. Set 0 untuk menonaktifkan.
    |
    */

    'selfie_retention' => [
        'orphan_grace_minutes' => (int) env('SELFIES_ORPHAN_GRACE_MINUTES', 60),
        'keep_days' => (int) env('SELFIES_KEEP_DAYS', 30),
        'path' => 'selfies',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Database
    |--------------------------------------------------------------------------
    |
    | Backup memakai mysqldump (MariaDB) atau dump SQL generik sebagai
    | fallback. File disimpan di disk privat, dikompres, lalu diputar
    | berdasarkan jumlah salinan yang disimpan.
    |
    */

    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'mysqldump' => env('BACKUP_MYSQLDUMP', null),
    ],

];
