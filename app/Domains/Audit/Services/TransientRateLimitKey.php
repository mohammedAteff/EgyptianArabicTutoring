<?php

namespace App\Domains\Audit\Services;

class TransientRateLimitKey
{
    /** Opaque security buckets only; callers keep the existing short expiration. */
    public static function make(string $scope, string $input): string
    {
        return $scope.':'.hash_hmac('sha256', $input, (string) config('app.key'));
    }
}
