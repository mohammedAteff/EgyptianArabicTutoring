<?php

namespace App\Domains\Resources\Models;

use App\Domains\CMS\Models\EntityTranslationRevision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    use HasFactory;

    protected $table = 'category_translations';

    protected $fillable = [
        'category_id',
        'locale',
        'source_revision_id',
        'status',
        'name',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(EntityTranslationRevision::class, 'source_revision_id');
    }
}
