<?php

namespace App\Domains\Resources\Models;

use App\Domains\CMS\Models\EntityTranslationRevision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceTranslation extends Model
{
    use HasFactory;

    protected $table = 'resource_translations';

    protected $fillable = [
        'resource_id',
        'locale',
        'source_revision_id',
        'status',
        'title',
        'short_description',
        'full_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'source_revision_id' => 'integer',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(EntityTranslationRevision::class, 'source_revision_id');
    }
}
