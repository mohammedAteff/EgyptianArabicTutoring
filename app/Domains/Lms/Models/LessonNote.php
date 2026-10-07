<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\LessonNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $student_id
 * @property int $lesson_id
 * @property string $body
 * @property int $lock_version
 */
class LessonNote extends Model
{
    /** @use HasFactory<LessonNoteFactory> */
    use HasFactory;

    protected $table = 'lms_lesson_notes';

    protected $guarded = ['*'];

    protected $hidden = ['body'];

    protected static function newFactory(): LessonNoteFactory
    {
        return LessonNoteFactory::new();
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
