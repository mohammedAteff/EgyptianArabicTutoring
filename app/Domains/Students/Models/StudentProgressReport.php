<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\StudentProgressReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProgressReport extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'date_from', 'date_to', 'summary', 'next_goals', 'student_visible', 'created_by'];

    protected static function newFactory(): StudentProgressReportFactory
    {
        return StudentProgressReportFactory::new();
    }

    protected function casts(): array
    {
        return ['date_from' => 'immutable_date', 'date_to' => 'immutable_date', 'student_visible' => 'boolean'];
    }
}
