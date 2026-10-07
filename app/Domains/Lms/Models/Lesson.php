<?php

namespace App\Domains\Lms\Models;

use Database\Factories\LmsLessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property int $course_id
 * @property int $section_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $lock_version
 * @property int|null $protection_profile_id
 */
class Lesson extends LmsModel
{
    use HasFactory;

    protected $table = 'lms_lessons';

    protected static function newFactory(): LmsLessonFactory
    {
        return LmsLessonFactory::new();
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<LessonBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->orderBy('sort_order')->orderBy('id');
    }
}
