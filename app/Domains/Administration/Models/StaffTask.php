<?php

namespace App\Domains\Administration\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\StaffTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTask extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'student_id', 'assignee_id', 'created_by', 'due_date', 'status', 'priority', 'completed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'immutable_date', 'completed_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): StaffTaskFactory
    {
        return StaffTaskFactory::new();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Administrator, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'assignee_id')->withTrashed();
    }
}
