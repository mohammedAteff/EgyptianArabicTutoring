<?php

namespace App\Domains\CMS\Models;

use App\Domains\Games\Models\Game;
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

        return $refs;
    }

    public function isReferenced(): bool
    {
        return ! empty($this->getReferences());
    }
}
