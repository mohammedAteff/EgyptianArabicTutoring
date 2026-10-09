<?php

namespace App\Domains\CMS\Models;

use Database\Factories\Domains\CMS\Models\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /** @return HasMany<Blog, $this> */
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'category_id');
    }
}
