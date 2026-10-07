<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Database\Factories\LessonBookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $student_id
 * @property int $lesson_id
 * @property int $block_id
 * @property int $position_milliseconds
 * @property string|null $label
 */
class LessonBookmark extends Model
{
    /** @use HasFactory<LessonBookmarkFactory> */
    use HasFactory;

    protected $table = 'lms_lesson_bookmarks';

    protected $guarded = ['*'];

    protected $hidden = ['label'];

    protected static function newFactory(): LessonBookmarkFactory
    {
        return LessonBookmarkFactory::new();
    }

    protected function casts(): array
    {
        return ['position_milliseconds' => 'integer'];
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

    /** @return BelongsTo<LessonBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(LessonBlock::class, 'block_id');
    }

    public function timestampLabel(): string
    {
        $seconds = intdiv($this->position_milliseconds, 1000);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
