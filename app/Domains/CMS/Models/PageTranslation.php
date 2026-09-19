<?php

namespace App\Domains\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    protected $table = 'page_translations';

    protected $fillable = [
        'page_id',
        'locale',
        'source_revision_id',
        'status',
        'title',
        'content',
        'excerpt',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'source_revision_id' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(EntityTranslationRevision::class, 'source_revision_id');
    }
}
