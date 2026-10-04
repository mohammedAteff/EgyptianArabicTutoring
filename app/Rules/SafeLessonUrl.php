<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeLessonUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value)) {
            $fail('Use a valid HTTPS link without embedded login credentials.');
        }
    }

    public static function isSafe(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $url)) {
            return false;
        }
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && ($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
