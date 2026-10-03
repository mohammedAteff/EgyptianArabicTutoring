<?php

namespace App\Domains\Students\Models;

use Database\Factories\StudentBinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentBin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['student_id', 'created_by_type', 'created_by_id', 'student_visible', 'title', 'body'];

    protected static function newFactory(): StudentBinFactory
    {
        return StudentBinFactory::new();
    }

    protected function casts(): array
    {
        return ['student_visible' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
