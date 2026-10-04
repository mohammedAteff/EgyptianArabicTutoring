<?php

namespace App\Domains\Students\Models;

use Database\Factories\Domains\Students\Models\EntitlementTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** @property string $code @property string $label @property bool $active */
class EntitlementType extends Model
{
    /** @use HasFactory<EntitlementTypeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'label', 'nominal_minutes', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'nominal_minutes' => 'integer'];
    }

    protected static function newFactory(): EntitlementTypeFactory
    {
        return EntitlementTypeFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (self $type): void {
            if ($type->isDirty('code')) {
                throw new RuntimeException('Entitlement codes are permanent.');
            }
        });
    }
}
