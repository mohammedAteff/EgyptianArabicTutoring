<?php

namespace App\Domains\CMS\Services;

use Illuminate\Support\Facades\Storage;

class AboutImageService
{
    public function url(?string $source): ?string
    {
        if ($source === null || preg_match('~[\x00-\x1f\x7f\\\\]~', $source)) {
            return null;
        }

        $source = trim($source);
        if ($source === '') {
            return null;
        }

        $parts = parse_url($source);
        if (is_array($parts) && isset($parts['scheme'])) {
            if (filter_var($source, FILTER_VALIDATE_URL) === false
                || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return null;
            }

            return $source;
        }

        $decoded = rawurldecode($source);
        if (str_starts_with($decoded, '/')
            || preg_match('~(?:^|/)\.\.(?:/|$)|[\x00-\x1f\x7f\\\\:?#]~', $decoded)
            || ! Storage::disk('public')->exists($source)) {
            return null;
        }

        return Storage::disk('public')->url($source);
    }
}
