<?php

namespace App\Domains\CMS\Models;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogRevision extends Model
{
    public $timestamps = false;

    protected $table = 'blog_revisions';

    protected $fillable = [
        'blog_id',
        'snapshot',
        'revised_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Blog, $this> */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class, 'blog_id');
    }

    /** @return BelongsTo<Administrator, $this> */
    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'revised_by');
    }
}
