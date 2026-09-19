<?php

namespace App\Domains\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqTranslation extends Model
{
    use HasFactory;

    protected $table = 'faq_translations';

    protected $fillable = [
        'faq_id',
        'locale',
        'source_revision_id',
        'status',
        'question',
        'answer',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'source_revision_id' => 'integer',
        ];
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }

    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(EntityTranslationRevision::class, 'source_revision_id');
    }
}
