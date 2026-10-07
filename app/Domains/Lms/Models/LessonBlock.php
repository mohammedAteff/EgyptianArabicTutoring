<?php

namespace App\Domains\Lms\Models;

use App\Domains\Resources\Models\Resource;
use Database\Factories\LmsLessonBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $lesson_id
 * @property string $kind
 * @property string $status
 * @property int|null $resource_id
 * @property array<string, mixed>|null $payload
 * @property int $lock_version
 */
class LessonBlock extends LmsModel
{
    use HasFactory;

    public const KINDS = ['bunny_video', 'youtube_video', 'external_video', 'rich_text', 'image', 'file', 'resource', 'external_link', 'quiz', 'assignment'];

    protected $table = 'lms_lesson_blocks';

    protected $hidden = ['payload'];

    protected static function newFactory(): LmsLessonBlockFactory
    {
        return LmsLessonBlockFactory::new();
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return BelongsTo<LmsAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(LmsAsset::class, 'asset_id');
    }
}
