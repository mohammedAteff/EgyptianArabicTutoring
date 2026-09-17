<?php

namespace App\Domains\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentRevision extends Model
{
    use HasFactory;

    protected $table = 'content_revisions';

    protected $fillable = [
        'revisable_type',
        'revisable_id',
        'revision_number',
        'title',
        'content',
        'created_by_id',
        'status', // 'draft', 'published'
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'content' => 'array',
        ];
    }

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }
}
