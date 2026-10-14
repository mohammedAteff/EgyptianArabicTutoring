<?php

namespace App\Domains\CMS\Models;

use Database\Factories\Domains\CMS\Models\WebsiteCopyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WebsiteCopy extends Model
{
    /** @use HasFactory<WebsiteCopyFactory> */
    use HasFactory;

    protected $fillable = ['page', 'locale', 'published_content', 'source_hash', 'version', 'published_at'];

    protected function casts(): array
    {
        return ['published_content' => 'array', 'version' => 'integer', 'published_at' => 'datetime'];
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable');
    }
}
