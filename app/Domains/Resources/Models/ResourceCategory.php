<?php

namespace App\Domains\Resources\Models;

use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceCategory extends Model
{
    use HasFactory, HasTranslations;

    public function getTranslationModelClass(): string
    {
        return CategoryTranslation::class;
    }

    public function getEntityType(): string
    {
        return 'category';
    }

    public function getForeignKey(): string
    {
        return 'category_id';
    }

    protected $table = 'resource_categories';

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'category_id');
    }
}
