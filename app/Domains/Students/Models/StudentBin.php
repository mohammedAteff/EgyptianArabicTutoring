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

    protected $attributes = ['revision' => 1];

    protected static function booted(): void
    {
        static::updating(function (StudentBin $bin): void {
            if ($bin->isDirty(['body', 'title', 'student_visible'])) {
                $bin->revision = (int) $bin->getOriginal('revision') + 1;
            }
        });
    }

    protected $fillable = ['student_id', 'created_by_type', 'created_by_id', 'student_visible', 'title', 'body'];

    protected static function newFactory(): StudentBinFactory
    {
        return StudentBinFactory::new();
    }

    protected function casts(): array
    {
        return ['student_visible' => 'boolean', 'revision' => 'integer'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
