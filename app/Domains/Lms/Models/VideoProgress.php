<?php

namespace App\Domains\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property int $student_id
 * @property int $block_id
 * @property string $media_hash
 * @property int $duration_milliseconds
 * @property array<int,array{int,int}> $watched_ranges
 * @property int $position_milliseconds
 * @property int $sequence
 * @property bool $playing
 * @property Carbon|null $sampled_at
 * @property string|null $watch_token_hash
 * @property int|null $lease_id
 */
class VideoProgress extends Model
{
    protected $table = 'lms_video_progress';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = ['*'];

    protected $hidden = ['watch_token_hash'];

    protected function casts(): array
    {
        return ['watched_ranges' => 'array', 'duration_milliseconds' => 'integer', 'position_milliseconds' => 'integer', 'sequence' => 'integer', 'playing' => 'boolean', 'sampled_at' => 'datetime'];
    }
}
