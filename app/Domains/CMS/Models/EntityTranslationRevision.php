<?php

namespace App\Domains\CMS\Models;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityTranslationRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'entity_translation_revisions';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'revision_number',
        'locale',
        'title',
        'description',
        'content',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'content' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }
}
