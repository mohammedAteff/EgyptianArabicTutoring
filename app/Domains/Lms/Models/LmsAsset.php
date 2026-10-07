<?php

namespace App\Domains\Lms\Models;

use Database\Factories\LmsAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $course_id
 * @property string $kind
 * @property string $status
 * @property string $disk
 * @property string|null $path
 * @property string $mime_type
 * @property int $byte_size
 * @property string $sha256
 */
class LmsAsset extends LmsModel
{
    use HasFactory;

    protected $table = 'lms_assets';

    protected $hidden = ['disk', 'path', 'original_name', 'sha256'];

    protected static function newFactory(): LmsAssetFactory
    {
        return LmsAssetFactory::new();
    }

    protected function casts(): array
    {
        return ['byte_size' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function usableFor(Course $course): bool
    {
        $owner = $this->course;

        return $this->status === 'active' && $owner !== null
            && ($owner->kind === 'catalog' || ($course->kind === 'private' && (int) $owner->owner_student_id === (int) $course->owner_student_id));
    }
}
