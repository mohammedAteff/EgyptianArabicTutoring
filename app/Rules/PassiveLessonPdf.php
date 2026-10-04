<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class PassiveLessonPdf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid() || $value->getSize() > 10 * 1024 * 1024) {
            $fail('Upload a PDF no larger than 10 MB.');

            return;
        }
        $bytes = file_get_contents($value->getPathname());
        if ($bytes === false || ! str_starts_with($bytes, '%PDF-') || ! preg_match('/%%EOF\s*\z/', $bytes)
            || preg_match('~/(?:JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b|<\s*(?:html|script)\b|#[0-9a-f]{2}~i', $bytes)) {
            $fail('Upload a complete PDF without scripts, automatic actions, or embedded files.');
        }
    }
}
