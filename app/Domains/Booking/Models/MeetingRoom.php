<?php

namespace App\Domains\Booking\Models;

use Database\Factories\MeetingRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRoom extends Model
{
    /** @use HasFactory<MeetingRoomFactory> */
    use HasFactory;

    protected static function newFactory(): MeetingRoomFactory
    {
        return MeetingRoomFactory::new();
    }

    protected $fillable = ['meeting_provider_id', 'name', 'url', 'url_hash', 'active', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<MeetingProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MeetingProvider::class, 'meeting_provider_id');
    }
}
