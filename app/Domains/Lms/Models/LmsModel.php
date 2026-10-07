<?php

namespace App\Domains\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

abstract class LmsModel extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new RuntimeException('LMS records retain history; use archive or revocation.'));
    }
}
