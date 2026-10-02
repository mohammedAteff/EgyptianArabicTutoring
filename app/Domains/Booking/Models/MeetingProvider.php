<?php

namespace App\Domains\Booking\Models;

use Database\Factories\MeetingProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingProvider extends Model
{
    /** @use HasFactory<MeetingProviderFactory> */
    use HasFactory;

    protected static function newFactory(): MeetingProviderFactory
    {
        return MeetingProviderFactory::new();
    }

    protected $fillable = ['name', 'icon', 'active', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(MeetingRoom::class);
    }
}
