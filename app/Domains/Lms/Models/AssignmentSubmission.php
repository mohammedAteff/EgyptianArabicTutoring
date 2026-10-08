<?php

namespace App\Domains\Lms\Models;

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
 */
class AssignmentSubmission extends LmsModel
{
    protected $table = 'lms_assignment_submissions';

    protected $guarded = ['*'];

    protected $hidden = ['definition', 'body', 'url', 'path', 'sha256', 'request_key'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'number' => 'integer', 'byte_size' => 'integer', 'lock_version' => 'integer'];
    }
}
