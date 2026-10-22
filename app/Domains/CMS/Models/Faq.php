<?php

namespace App\Domains\CMS\Models;

use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Faq extends Model
{
    /** @use HasTranslations<FaqTranslation> */
    use HasFactory, HasTranslations;

    /** @return class-string<FaqTranslation> */
    public function getTranslationModelClass(): string
    {
        return FaqTranslation::class;
    }

    public function getEntityType(): string
    {
        return 'faq';
    }

    protected $table = 'faqs';

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'active',
        'topic',
        'featured',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    /** @return MorphMany<ContentRevision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable')->orderByDesc('revision_number');
    }
}
