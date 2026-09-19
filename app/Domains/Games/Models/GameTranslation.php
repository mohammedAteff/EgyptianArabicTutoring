<?php

namespace App\Domains\Games\Models;

use App\Domains\CMS\Models\EntityTranslationRevision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTranslation extends Model
{
    use HasFactory;

    protected $table = 'game_translations';

    protected $fillable = [
        'game_id',
        'locale',
        'source_revision_id',
        'status',
        'title',
        'description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'source_revision_id' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(EntityTranslationRevision::class, 'source_revision_id');
    }
}
