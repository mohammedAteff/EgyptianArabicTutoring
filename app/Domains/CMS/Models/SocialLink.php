<?php

namespace App\Domains\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    use HasFactory;

    protected $table = 'social_links';

    protected $fillable = [
        'platform', // 'instagram', 'tiktok', 'youtube', 'telegram', 'whatsapp'
        'url_or_phone',
        'label',
        'default_message',
        'enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('sort_order');
    }

    public function getFormattedUrl(): string
    {
        if ($this->platform === 'whatsapp') {
            // Strip non-numeric characters from phone
            $cleanPhone = preg_replace('/[^0-9]/', '', $this->url_or_phone);
            $url = "https://wa.me/{$cleanPhone}";
            if (! empty($this->default_message)) {
                $url .= '?text='.urlencode($this->default_message);
            }

            return $url;
        }

        if ($this->platform === 'telegram' && ! str_starts_with($this->url_or_phone, 'http')) {
            $cleanHandle = ltrim($this->url_or_phone, '@');

            return "https://t.me/{$cleanHandle}";
        }

        return $this->url_or_phone;
    }
}
