<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_HADIR = 'hadir';

    public const STATUS_TERLAMBAT = 'terlambat';

    public const STATUS_IZIN = 'izin';

    public const STATUS_SAKIT = 'sakit';

    public const STATUS_ALPHA = 'alpha';

    public const STATUS_LIBUR = 'libur';

    protected $fillable = [
        'employee_id',
        'location_id',
        'location_validation_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_distance',
        'check_in_accuracy',
        'check_in_selfie',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_distance',
        'check_out_accuracy',
        'check_out_selfie',
        'work_minutes',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_in_distance' => 'float',
            'check_in_accuracy' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'check_out_distance' => 'float',
            'check_out_accuracy' => 'float',
            'work_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'location_id');
    }

    public function validation(): BelongsTo
    {
        return $this->belongsTo(LocationValidation::class, 'location_validation_id');
    }

    public function scopeForDate(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('attendance_date', $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : $date);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    public function hasCheckedIn(): bool
    {
        return $this->check_in_at !== null;
    }

    public function hasCheckedOut(): bool
    {
        return $this->check_out_at !== null;
    }

    public function isComplete(): bool
    {
        return $this->hasCheckedIn() && $this->hasCheckedOut();
    }

    public function isLate(): bool
    {
        return $this->status === self::STATUS_TERLAMBAT;
    }

    public function calculateWorkMinutes(): int
    {
        if (! $this->isComplete()) {
            return 0;
        }

        return (int) round(
            $this->check_in_at->diffInMinutes($this->check_out_at, false)
        );
    }
}
