<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    use HasFactory;

    protected $table = 'daily_metrics';

    protected $fillable = [
        'metric_date',
        'metric_name',
        'dimension_key',
        'dimension_value',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'count' => 'integer',
        ];
    }
}
