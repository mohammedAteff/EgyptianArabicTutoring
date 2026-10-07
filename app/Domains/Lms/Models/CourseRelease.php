<?php

namespace App\Domains\Lms\Models;

use App\Domains\CMS\Models\ContentRevision;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/** @property int $course_id
 * @property int $content_revision_id
 * @property int $revision_number
 * @property string $content_hash
 */
class CourseRelease extends LmsModel
{
    protected $table = 'lms_course_releases';

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'revision_number' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn (): never => throw new RuntimeException('Published course releases are immutable.'));
    }
}
