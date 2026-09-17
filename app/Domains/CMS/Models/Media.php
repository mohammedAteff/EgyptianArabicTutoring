<?php

namespace App\Domains\CMS\Models;

use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'dimensions',
        'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'dimensions' => 'array',
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get all active references to this media asset across pages, resources, games, and settings.
     *
     * @return array<int, string>
     */
    public function getReferences(): array
    {
        $refs = [];

        // Check Pages
        $pages = Page::query()
            ->where('og_image_path', $this->path)
            ->orWhere('og_image_path', $this->url())
            ->get(['id', 'title']);
        foreach ($pages as $p) {
            $refs[] = "Page: {$p->title}";
        }

        // Check Resources
        $resources = \App\Domains\Resources\Models\Resource::query()
            ->where('cover_image_path', $this->path)
            ->orWhere('cover_image_path', $this->url())
            ->orWhere('file_path', $this->path)
            ->get(['id', 'title']);
        foreach ($resources as $r) {
            $refs[] = "Resource: {$r->title}";
        }

        // Check Games
        $games = Game::query()
            ->where('thumbnail_path', $this->path)
            ->orWhere('thumbnail_path', $this->url())
            ->get(['id', 'title']);
        foreach ($games as $g) {
            $refs[] = "Game: {$g->title}";
        }

        // Check Settings
        $settings = Setting::query()
            ->where('value', $this->path)
            ->orWhere('value', $this->url())
            ->get(['key']);
        foreach ($settings as $s) {
            $refs[] = "Setting: {$s->key}";
        }

        // Check Content Revisions
        $revisions = ContentRevision::query()
            ->where('content', 'like', '%'.$this->path.'%')
            ->orWhere('content', 'like', '%'.$this->url().'%')
            ->get(['id', 'revisable_type', 'revision_number']);
        foreach ($revisions as $rev) {
            $type = class_basename($rev->revisable_type);
            $refs[] = "Revision: {$type} #{$rev->revision_number}";
        }

        return $refs;
    }

    public function isReferenced(): bool
    {
        return ! empty($this->getReferences());
    }

    /**
     * Determine if a storage path is referenced across any CMS entities or media library.
     */
    public static function isPathReferenced(string $path, ?int $ignoreResourceId = null): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        // Check Media library record
        $media = static::query()
            ->where('path', $normalized)
            ->orWhere('path', $path)
            ->first();
        if ($media && $media->isReferenced()) {
            return true;
        }

        // Check Pages
        if (Page::query()->where('og_image_path', $normalized)->orWhere('og_image_path', $path)->exists()) {
            return true;
        }

        // Check Resources (optionally ignoring one resource)
        $resourceQuery = \App\Domains\Resources\Models\Resource::query()
            ->where(function ($q) use ($normalized, $path) {
                $q->where('cover_image_path', $normalized)
                    ->orWhere('cover_image_path', $path)
                    ->orWhere('file_path', $normalized)
                    ->orWhere('file_path', $path);
            });
        if ($ignoreResourceId !== null) {
            $resourceQuery->where('id', '!=', $ignoreResourceId);
        }
        if ($resourceQuery->exists()) {
            return true;
        }

        // Check Games
        if (Game::query()->where('thumbnail_path', $normalized)->orWhere('thumbnail_path', $path)->exists()) {
            return true;
        }

        // Check Settings
        if (Setting::query()->where('value', $normalized)->orWhere('value', $path)->exists()) {
            return true;
        }

        // Check Content Revisions
        if (ContentRevision::query()
            ->where(function ($q) use ($normalized, $path) {
                $q->where('content', 'like', '%'.$normalized.'%')
                    ->orWhere('content', 'like', '%'.$path.'%');
            })->exists()) {
            return true;
        }

        return false;
    }
}
