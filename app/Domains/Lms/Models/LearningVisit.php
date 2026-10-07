<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\LearningVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $course_id
 * @property int|null $lesson_id
 * @property Carbon $accessed_at
 */
class LearningVisit extends Model
{
    /** @use HasFactory<LearningVisitFactory> */
    use HasFactory;

    protected $table = 'lms_learning_visits';

    protected $guarded = ['*'];

    protected static function newFactory(): LearningVisitFactory
    {
        return LearningVisitFactory::new();
    }

    protected function casts(): array
    {
        return ['accessed_at' => 'datetime'];
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

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
