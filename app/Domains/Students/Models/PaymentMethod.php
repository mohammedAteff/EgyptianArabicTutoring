<?php

namespace App\Domains\Students\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected static function newFactory(): PaymentMethodFactory
    {
        return PaymentMethodFactory::new();
    }

    protected $fillable = ['name', 'active', 'sort_order', 'is_default'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('active', true)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }
}
