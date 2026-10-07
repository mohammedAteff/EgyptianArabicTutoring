<?php

namespace App\Domains\Lms\Models;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $course_id
 * @property int|null $section_id
 * @property int|null $lesson_id
 * @property string $source_kind
 * @property string $source_key
 * @property string $issuance_fingerprint
 * @property string $access_mode
 * @property string $status
 * @property Carbon $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $relative_days
 * @property int $lock_version
 */
class AccessGrant extends LmsModel
{
    protected $table = 'lms_access_grants';

    protected $hidden = ['issuance_fingerprint'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'relative_days' => 'integer', 'metadata' => 'array', 'lock_version' => 'integer'];
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

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasOne<LearningAssignment, $this> */
    public function learningAssignment(): HasOne
    {
        return $this->hasOne(LearningAssignment::class);
    }
}
