<?php

namespace App\Domains\Administration\Models;

use Database\Factories\StaffBinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffBin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['author_id', 'title', 'body', 'pinned', 'pinned_by', 'pinned_at'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'pinned_at' => 'immutable_datetime'];
    }

    /** @return HasMany<StaffNotePreference, $this> */
    public function preferences(): HasMany
    {
        return $this->hasMany(StaffNotePreference::class);
    }

    /** @return BelongsTo<Administrator, $this> */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'pinned_by')->withTrashed();
    }

    protected static function newFactory(): StaffBinFactory
    {
        return StaffBinFactory::new();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'author_id')->withTrashed();
    }
}
