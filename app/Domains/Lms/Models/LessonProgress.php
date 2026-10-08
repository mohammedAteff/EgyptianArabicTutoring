<?php

namespace App\Domains\Lms\Models;

use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $lesson_id
 * @property string $requirement_hash
 * @property Carbon $started_at
 * @property Carbon|null $manual_completed_at
 * @property Carbon|null $completed_at
 */
class LessonProgress extends LmsModel
{
    protected $table = 'lms_lesson_progress';

    protected $guarded = ['*'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'manual_completed_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
