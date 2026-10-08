<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttendanceLocation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Batas jarak check-in dari titik koordinat lokasi (dalam meter).
     */
    public const MIN_RADIUS_METERS = 20;

    public const MAX_RADIUS_METERS = 100000;

    public const DEFAULT_RADIUS_METERS = 100000;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius',
        'public_token',
        'status',
        'qr_rotated_at',
    ];

    protected $hidden = [
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius' => 'integer',
            'qr_rotated_at' => 'datetime',
        ];
    }

    /**
     * Token QR dibuat satu kali saat lokasi pertama kali dibuat dan tidak pernah
     * berubah akibat edit data lokasi. Token hanya berganti bila admin secara
     * sadar memanggil rotateToken() dari halaman QR.
     */
    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            $location->public_token ??= self::generateToken();
        });
    }

    public static function generateToken(): string
    {
        return 'loc_'.Str::random(40);
    }

    public function rotateToken(): string
    {
        $this->forceFill([
            'public_token' => self::generateToken(),
            'qr_rotated_at' => now(),
        ])->save();

        return $this->public_token;
    }

    public function qrPayload(): string
    {
        return sprintf('ABSENSI:%s', $this->public_token);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_locations', 'location_id', 'employee_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
