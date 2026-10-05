<?php

namespace App\Domains\Students\Models;

use App\Domains\Administration\Models\Administrator;
use Database\Factories\StudentOperationalAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentOperationalAlert extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'created_by', 'updated_by', 'title', 'body', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): StudentOperationalAlertFactory
    {
        return StudentOperationalAlertFactory::new();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Administrator, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by')->withTrashed();
    }
}
