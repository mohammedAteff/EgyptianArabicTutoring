<?php

namespace App\Domains\Financial\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReasonCode extends Model
{
    protected $fillable = [
        'category',
        'code',
        'label',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<ReasonCode>  $query
     * @return Builder<ReasonCode>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<ReasonCode>  $query
     * @return Builder<ReasonCode>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->orderBy('sort_order')->orderBy('label');
    }
}
