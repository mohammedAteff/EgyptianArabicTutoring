<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\EntityTranslationRevision;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\PageTranslation;
use Illuminate\Support\Facades\DB;

class ContentService
{
    /**
     * Create a canonical page with localized translations and initial source revision.
     */
    public function createPage(array $data): Page
    {
        return DB::transaction(function () use ($data) {
            $slug = $data['slug'];

            $enData = $data['en'] ?? [];
            $enTitle = $enData['title'] ?? ($data['title'] ?? ucfirst($slug));
            $enContent = $enData['content'] ?? ($data['content'] ?? '');

            $page = Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $enTitle,
                    'content' => is_array($enContent) ? json_encode($enContent) : $enContent,
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );

            // 1. Seed initial Revision 1 in entity_translation_revisions
            EntityTranslationRevision::firstOrCreate(
                [
                    'entity_type' => 'page',
                    'entity_id' => $page->id,
                    'revision_number' => 1,
                ],
                [
                    'locale' => 'en',
                    'title' => $enTitle,
                    'content' => is_array($enContent) ? $enContent : ['body' => $enContent],
                    'created_at' => now(),
                ]
            );

            // 2. English live translation
            PageTranslation::firstOrCreate(
                [
                    'page_id' => $page->id,
                    'locale' => 'en',
                ],
                [
                    'title' => $enTitle,
                    'content' => is_array($enContent) ? json_encode($enContent) : $enContent,
                    'source_revision_id' => 1,
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );

            // 3. Other provided locales (e.g. 'fr', 'de')
            foreach (['fr', 'de'] as $locale) {
                if (isset($data[$locale])) {
                    $locData = $data[$locale];
                    $locTitle = $locData['title'] ?? '';
                    $locContent = $locData['content'] ?? '';

                    PageTranslation::firstOrCreate(
                        [
                            'page_id' => $page->id,
                            'locale' => $locale,
                        ],
                        [
                            'title' => $locTitle,
                            'content' => is_array($locContent) ? json_encode($locContent) : $locContent,
                            'source_revision_id' => 1,
                            'status' => 'published',
                            'published_at' => now(),
                        ]
                    );
                }
            }

            return $page->fresh(['translations', 'sourceRevisions']);
        });
    }

    /**
     * Update English source content, advancing to a new revision and transitioning existing translations to stale.
     */
    public function updateEnglishSource(Page $page, array $data): Page
    {
        return DB::transaction(function () use ($page, $data) {
            $page = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();

            $currentRev = EntityTranslationRevision::where('entity_type', 'page')
                ->where('entity_id', $page->id)
                ->max('revision_number') ?: 1;

            $newRevNumber = $currentRev + 1;
            $newTitle = $data['title'] ?? $page->title;
            $newContent = $data['content'] ?? $page->content;

            // 1. Create immutable snapshot in entity_translation_revisions
            EntityTranslationRevision::create([
                'entity_type' => 'page',
                'entity_id' => $page->id,
                'revision_number' => $newRevNumber,
                'locale' => 'en',
                'title' => $newTitle,
                'content' => is_array($newContent) ? $newContent : ['body' => $newContent],
                'created_at' => now(),
            ]);

            // 2. Update page record
            $page->update([
                'title' => $newTitle,
                'content' => is_array($newContent) ? json_encode($newContent) : $newContent,
            ]);

            // 3. Update English translation
            PageTranslation::where('page_id', $page->id)
                ->where('locale', 'en')
                ->update([
                    'title' => $newTitle,
                    'content' => is_array($newContent) ? json_encode($newContent) : $newContent,
                    'source_revision_id' => $newRevNumber,
                ]);

            // 4. Transition French and German published translations to stale
            PageTranslation::where('page_id', $page->id)
                ->where('locale', '!=', 'en')
                ->where('status', 'published')
                ->update(['status' => 'stale']);

            return $page->fresh(['translations', 'sourceRevisions']);
        });
    }
}
