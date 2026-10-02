<?php

namespace App\Domains\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            if (in_array($setting->key, ['maintenance_mode', 'system.maintenance_mode'], true)) {
                Cache::forget('maintenance_mode_active');
                DB::afterCommit(fn () => Cache::forget('maintenance_mode_active'));
            }
            if ($setting->key === 'business_timezone') {
                Cache::forget('active_business_tz');
                DB::afterCommit(fn () => Cache::forget('active_business_tz'));
            }
        });
        static::deleted(function (self $setting): void {
            if (in_array($setting->key, ['maintenance_mode', 'system.maintenance_mode'], true)) {
                Cache::forget('maintenance_mode_active');
                DB::afterCommit(fn () => Cache::forget('maintenance_mode_active'));
            }
            if ($setting->key === 'business_timezone') {
                Cache::forget('active_business_tz');
                DB::afterCommit(fn () => Cache::forget('active_business_tz'));
            }
        });
    }

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting || $setting->value === null) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    public static function set(string $key, mixed $value, string $group = 'general', bool $isPublic = false): void
    {
        $encoded = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $encoded,
                'group' => $group,
                'is_public' => $isPublic,
            ]
        );

        if (in_array($key, ['system.maintenance_mode', 'maintenance_mode'], true)) {
            Cache::forget('system.maintenance_mode');
            Cache::forget('maintenance_mode_active');
        }
    }
}
