<?php

namespace App\Domains\Lms\Models;

use Database\Factories\LmsSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property int $course_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property int $lock_version
 */
class Section extends LmsModel
{
    use HasFactory;

    protected $table = 'lms_sections';

    protected static function newFactory(): LmsSectionFactory
    {
        return LmsSectionFactory::new();
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

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order')->orderBy('id');
    }
}
