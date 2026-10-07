<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $course_id
 * @property string $status
 * @property Carbon $enrolled_at
 */
class Enrollment extends LmsModel
{
    protected $table = 'lms_enrollments';

    protected function casts(): array
    {
        return ['enrolled_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
