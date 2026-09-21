<?php

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\ContentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add the supplied legal-review disclosure only to the known legacy
     * canonical policy text, while preserving authored policy changes.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasTable('page_translations')) {
            return;
        }

        $page = Page::query()->where('slug', 'privacy')->first();

        if (! $page) {
            return;
        }

        $translation = $page->translations()
            ->where('locale', 'en')
            ->where('status', 'published')
            ->first();

        if (! $translation) {
            return;
        }

        $disclosure = '(Note: This policy provides an operational disclosure of platform practices and should be reviewed by qualified legal counsel.)';
        $content = (string) $translation->content;

        if (Str::contains($content, $disclosure) || ! Str::contains($content, 'Intake notes and learning logs are retained during your studies')) {
            return;
        }

        app(ContentService::class)->updateEnglishSource($page, [
            'title' => $translation->title,
            'content' => rtrim($content).' '.$disclosure,
        ]);
    }

    /**
     * Do not remove a policy disclosure or authored revision on rollback.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
