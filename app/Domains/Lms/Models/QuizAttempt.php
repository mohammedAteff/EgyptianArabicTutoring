<?php

namespace App\Domains\Lms\Models;

use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $block_id
 * @property string $definition_hash
 * @property string $request_key
 * @property int $number
 * @property int $original_number
 * @property array<string,mixed> $definition
 * @property array<int,mixed>|null $answers
 * @property array<int,int|null>|null $marks
 * @property string $status
 * @property float|null $score
 * @property bool|null $passed
 * @property int $lock_version
 * @property string|null $feedback
 * @property Carbon|null $updated_at
 * @property Carbon|null $graded_at
 */
class QuizAttempt extends LmsModel
{
    protected $table = 'lms_quiz_attempts';

    protected $guarded = ['*'];

    protected $hidden = ['definition', 'answers', 'marks', 'request_key'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'answers' => 'array', 'marks' => 'array', 'score' => 'float', 'passed' => 'boolean', 'number' => 'integer', 'lock_version' => 'integer', 'submitted_at' => 'datetime', 'graded_at' => 'datetime'];
    }
}
