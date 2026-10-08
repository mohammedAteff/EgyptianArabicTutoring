<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $block_id
 * @property string $definition_hash
 * @property string $request_key
 * @property int $number
 * @property int $original_number
 * @property array<string,mixed> $definition
 * @property string $status
 * @property string $kind
 * @property string|null $body
 * @property string|null $url
 * @property string|null $path
 * @property string|null $mime_type
 * @property int|null $byte_size
 * @property string|null $sha256
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $reviewed_at
 * @property Student|null $student
 * @property LessonBlock|null $block
 */
class AssignmentSubmission extends LmsModel
{
    protected $table = 'lms_assignment_submissions';

    protected $guarded = ['*'];

    protected $hidden = ['definition', 'body', 'url', 'path', 'sha256', 'request_key'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'number' => 'integer', 'byte_size' => 'integer', 'lock_version' => 'integer', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<LessonBlock,$this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(LessonBlock::class, 'block_id');
    }
}
