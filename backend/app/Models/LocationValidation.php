<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationValidation extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'location_id',
        'employee_device_id',
        'ticket',
        'status',
        'issued_at',
        'expires_at',
        'consumed_at',
        'latitude',
        'longitude',
        'distance',
        'accuracy',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance' => 'float',
            'accuracy' => 'float',
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(EmployeeDevice::class, 'employee_device_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ISSUED && ! $this->isExpired();
    }
}
