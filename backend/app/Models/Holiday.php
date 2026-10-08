<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'name', 'description'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function isHoliday(\DateTimeInterface $date): bool
    {
        return static::query()
            ->whereDate('date', $date->format('Y-m-d'))
            ->exists();
    }
}
