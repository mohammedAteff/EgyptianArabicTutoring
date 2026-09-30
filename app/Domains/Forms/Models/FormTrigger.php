<?php

declare(strict_types=1);

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class FormTrigger extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'form_id',
        'trigger_name',
    ];

    public const array ALLOWED_TRIGGERS = [
        'pre_booking',
        'after_booking',
        'after_reschedule',
        'next_session_check',
    ];

    protected static function booted(): void
    {
        static::saving(function (FormTrigger $trigger) {
            if (! in_array($trigger->trigger_name, self::ALLOWED_TRIGGERS, true)) {
                throw new InvalidArgumentException("Disallowed trigger name: [{$trigger->trigger_name}]");
            }
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
