<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_start',
        'work_end',
        'tolerance_minutes',
        'radius_meter',
        'max_accuracy_meter',
        'ticket_ttl_seconds',
        'max_ticket_per_day',
        'require_selfie',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'tolerance_minutes' => 'integer',
            'radius_meter' => 'integer',
            'max_accuracy_meter' => 'float',
            'ticket_ttl_seconds' => 'integer',
            'max_ticket_per_day' => 'integer',
            'require_selfie' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'tolerance_minutes' => 15,
            'radius_meter' => AttendanceLocation::DEFAULT_RADIUS_METERS,
            'max_accuracy_meter' => 50,
            'ticket_ttl_seconds' => 120,
            'max_ticket_per_day' => 10,
            'require_selfie' => true,
            'timezone' => 'Asia/Jakarta',
        ]);
    }
}
