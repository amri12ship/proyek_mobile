<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_token',
        'device_name',
        'platform',
        'app_version',
        'status',
        'registered_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
