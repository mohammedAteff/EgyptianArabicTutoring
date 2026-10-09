<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BlogImageService
{
    public function url(?string $path): ?string
    {
        if (! $path || ! $this->isImage($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function assertSelectable(?string $path): void
    {
        if ($path !== null && $path !== '' && (! Media::query()->where('disk', 'public')->where('path', $path)->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->exists() || ! $this->isImage($path))) {
            throw ValidationException::withMessages(['featured_image_path' => 'Choose an existing JPEG, PNG, WebP or GIF image from the media library, or remove the image.']);
        }
    }

    private function isImage(string $path): bool
    {
        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || ! Storage::disk('public')->exists($path)) {
            return false;
        }
        $image = @getimagesize(Storage::disk('public')->path($path));

        return is_array($image) && in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }
}
