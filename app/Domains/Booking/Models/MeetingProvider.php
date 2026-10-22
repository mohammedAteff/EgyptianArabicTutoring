<?php

namespace App\Domains\Booking\Models;

use Database\Factories\MeetingProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingProvider extends Model
{
    /** @use HasFactory<MeetingProviderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function newFactory(): MeetingProviderFactory
    {
        return MeetingProviderFactory::new();
    }

    protected $fillable = ['name', 'icon', 'active', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_default' => 'boolean'];
    }

    /** @return HasMany<MeetingRoom, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(MeetingRoom::class);
    }
}
