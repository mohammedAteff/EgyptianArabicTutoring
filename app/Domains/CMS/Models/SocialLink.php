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
            if (! preg_match('/^\+?[1-9][0-9\s().-]{6,22}$/', trim($this->url_or_phone))) {
                return '#';
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', $this->url_or_phone);
            if (strlen((string) $cleanPhone) < 8 || strlen((string) $cleanPhone) > 15) {
                return '#';
            }
            $url = "https://wa.me/{$cleanPhone}";
            if (! empty($this->default_message)) {
                $url .= '?text='.urlencode($this->default_message);
            }

            return $url;
        }

        if ($this->platform === 'telegram' && preg_match('/^@?[A-Za-z0-9_]{5,32}$/', trim($this->url_or_phone))) {
            $cleanHandle = ltrim(trim($this->url_or_phone), '@');

            return "https://t.me/{$cleanHandle}";
        }

        $parts = parse_url(trim($this->url_or_phone));
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return '#';
        }

        return trim($this->url_or_phone);
    }
}
