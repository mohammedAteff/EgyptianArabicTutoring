<?php

namespace App\Domains\Availability\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityException extends Model
{
    use HasFactory;

    protected $table = 'availability_exceptions';

    protected $fillable = [
        'date',
        'type', // 'blocked', 'special_hours'
        'start_time',
        'end_time',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->type === 'blocked';
    }

    public function isSpecialHours(): bool
    {
        return $this->type === 'special_hours';
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }
}
