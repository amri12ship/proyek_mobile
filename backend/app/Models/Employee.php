<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_id',
        'nik',
        'name',
        'department_id',
        'position_id',
        'phone',
        'gender',
        'hire_date',
        'photo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(AttendanceLocation::class, 'employee_locations', 'employee_id', 'location_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AttendanceSchedule::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(LocationValidation::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
