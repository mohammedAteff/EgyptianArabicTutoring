<?php

namespace App\Domains\Lms\Models;

use RuntimeException;

/** @property string $fingerprint
 * @property int|null $access_grant_id
 * @property int|null $learning_assignment_id
 */
class AccessEvent extends LmsModel
{
    public $timestamps = false;

    protected $table = 'lms_access_events';

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(fn (): never => throw new RuntimeException('LMS access events are append-only.'));
    }
}
