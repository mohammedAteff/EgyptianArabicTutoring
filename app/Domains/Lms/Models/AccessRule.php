<?php

namespace App\Domains\Lms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $course_id
 * @property string $audience
 * @property string $access_mode
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $relative_days
 */
class AccessRule extends LmsModel
{
    protected $table = 'lms_access_rules';

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'relative_days' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
