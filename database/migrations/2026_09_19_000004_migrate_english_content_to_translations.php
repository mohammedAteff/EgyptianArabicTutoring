<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now()->toDateTimeString();

        // 1. Backfill Resources
        $resources = DB::table('resources')->get();
        foreach ($resources as $resource) {
            $status = $resource->status === 'published' ? 'published' : 'draft';
            $publishedAt = $resource->published_at ?? ($status === 'published' ? $resource->created_at : null);

            // Seed Revision 1 snapshot if not already present
            if (! DB::table('entity_translation_revisions')->where('entity_type', 'resource')->where('entity_id', $resource->id)->where('revision_number', 1)->exists()) {
                DB::table('entity_translation_revisions')->insert([
                    'entity_type' => 'resource',
                    'entity_id' => $resource->id,
                    'revision_number' => 1,
                    'locale' => 'en',
                    'title' => $resource->title,
                    'description' => $resource->short_description,
                    'content' => json_encode([
                        'title' => $resource->title,
                        'short_description' => $resource->short_description,
                        'full_description' => $resource->full_description,
                    ]),
                    'created_by' => null,
                    'created_at' => $resource->created_at ?? $now,
                ]);
            }

            // Seed English translation row if not already present
            if (! DB::table('resource_translations')->where('resource_id', $resource->id)->where('locale', 'en')->exists()) {
                DB::table('resource_translations')->insert([
                    'resource_id' => $resource->id,
                    'locale' => 'en',
                    'source_revision_id' => 1,
                    'status' => $status,
                    'title' => $resource->title,
                    'short_description' => $resource->short_description,
                    'full_description' => $resource->full_description,
                    'published_at' => $publishedAt,
                    'created_at' => $resource->created_at ?? $now,
                    'updated_at' => $resource->updated_at ?? $now,
                ]);
            }
        }

        // 2. Backfill Games
        $games = DB::table('games')->get();
        foreach ($games as $game) {
            $status = $game->status === 'available' ? 'published' : 'draft';

            // Seed Revision 1 snapshot if not already present
            if (! DB::table('entity_translation_revisions')->where('entity_type', 'game')->where('entity_id', $game->id)->where('revision_number', 1)->exists()) {
                DB::table('entity_translation_revisions')->insert([
                    'entity_type' => 'game',
                    'entity_id' => $game->id,
                    'revision_number' => 1,
                    'locale' => 'en',
                    'title' => $game->title,
                    'description' => $game->description,
                    'content' => json_encode([
                        'title' => $game->title,
                        'description' => $game->description,
                    ]),
                    'created_by' => null,
                    'created_at' => $game->created_at ?? $now,
                ]);
            }

            // Seed English translation row if not already present
            if (! DB::table('game_translations')->where('game_id', $game->id)->where('locale', 'en')->exists()) {
                DB::table('game_translations')->insert([
                    'game_id' => $game->id,
                    'locale' => 'en',
                    'source_revision_id' => 1,
                    'status' => $status,
                    'title' => $game->title,
                    'description' => $game->description,
                    'published_at' => $status === 'published' ? ($game->created_at ?? $now) : null,
                    'created_at' => $game->created_at ?? $now,
                    'updated_at' => $game->updated_at ?? $now,
                ]);
            }
        }

        // 3. Backfill Pages
        $pages = DB::table('pages')->get();
        foreach ($pages as $page) {
            $status = $page->status === 'published' ? 'published' : 'draft';
            $publishedAt = $page->published_at ?? ($status === 'published' ? $page->created_at : null);

            // Seed Revision 1 snapshot if not already present
            if (! DB::table('entity_translation_revisions')->where('entity_type', 'page')->where('entity_id', $page->id)->where('revision_number', 1)->exists()) {
                DB::table('entity_translation_revisions')->insert([
                    'entity_type' => 'page',
                    'entity_id' => $page->id,
                    'revision_number' => 1,
                    'locale' => 'en',
                    'title' => $page->title,
                    'description' => $page->excerpt ?? $page->seo_description,
                    'content' => json_encode([
                        'title' => $page->title,
                        'content' => $page->content,
                        'excerpt' => $page->excerpt,
                        'seo_title' => $page->seo_title,
                        'seo_description' => $page->seo_description,
                    ]),
                    'created_by' => null,
                    'created_at' => $page->created_at ?? $now,
                ]);
            }

            // Seed English translation row if not already present
            if (! DB::table('page_translations')->where('page_id', $page->id)->where('locale', 'en')->exists()) {
                DB::table('page_translations')->insert([
                    'page_id' => $page->id,
                    'locale' => 'en',
                    'source_revision_id' => 1,
                    'status' => $status,
                    'title' => $page->title,
                    'content' => $page->content,
                    'excerpt' => $page->excerpt,
                    'seo_title' => $page->seo_title,
                    'seo_description' => $page->seo_description,
                    'published_at' => $publishedAt,
                    'created_at' => $page->created_at ?? $now,
                    'updated_at' => $page->updated_at ?? $now,
                ]);
            }
        }

        // 4. Backfill FAQs
        $faqs = DB::table('faqs')->get();
        foreach ($faqs as $faq) {
            $status = $faq->active ? 'published' : 'draft';

            // Seed Revision 1 snapshot if not already present
            if (! DB::table('entity_translation_revisions')->where('entity_type', 'faq')->where('entity_id', $faq->id)->where('revision_number', 1)->exists()) {
                DB::table('entity_translation_revisions')->insert([
                    'entity_type' => 'faq',
                    'entity_id' => $faq->id,
                    'revision_number' => 1,
                    'locale' => 'en',
                    'title' => $faq->question,
                    'description' => null,
                    'content' => json_encode([
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                    ]),
                    'created_by' => null,
                    'created_at' => $faq->created_at ?? $now,
                ]);
            }

            // Seed English translation row if not already present
            if (! DB::table('faq_translations')->where('faq_id', $faq->id)->where('locale', 'en')->exists()) {
                DB::table('faq_translations')->insert([
                    'faq_id' => $faq->id,
                    'locale' => 'en',
                    'source_revision_id' => 1,
                    'status' => $status,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'published_at' => $status === 'published' ? ($faq->created_at ?? $now) : null,
                    'created_at' => $faq->created_at ?? $now,
                    'updated_at' => $faq->updated_at ?? $now,
                ]);
            }
        }

        // 5. Backfill Categories
        $categories = DB::table('resource_categories')->get();
        foreach ($categories as $cat) {
            $status = $cat->active ? 'published' : 'draft';

            // Seed Revision 1 snapshot if not already present
            if (! DB::table('entity_translation_revisions')->where('entity_type', 'category')->where('entity_id', $cat->id)->where('revision_number', 1)->exists()) {
                DB::table('entity_translation_revisions')->insert([
                    'entity_type' => 'category',
                    'entity_id' => $cat->id,
                    'revision_number' => 1,
                    'locale' => 'en',
                    'title' => $cat->name,
                    'description' => null,
                    'content' => json_encode([
                        'name' => $cat->name,
                        'description' => null,
                    ]),
                    'created_by' => null,
                    'created_at' => $cat->created_at ?? $now,
                ]);
            }

            // Seed English translation row if not already present
            if (! DB::table('category_translations')->where('category_id', $cat->id)->where('locale', 'en')->exists()) {
                DB::table('category_translations')->insert([
                    'category_id' => $cat->id,
                    'locale' => 'en',
                    'source_revision_id' => 1,
                    'status' => $status,
                    'name' => $cat->name,
                    'description' => null,
                    'published_at' => $status === 'published' ? ($cat->created_at ?? $now) : null,
                    'created_at' => $cat->created_at ?? $now,
                    'updated_at' => $cat->updated_at ?? $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Non-destructive rollback guard:
     * If human-authored content, non-English translations, or post-backfill revisions exist,
     * rollback fails explicitly with RuntimeException to prevent data loss and keep migration state synchronized.
     */
    public function down(): void
    {
        $hasAuthoredContent = false;

        // 1. Check if any non-English translations exist
        foreach (['resource_translations', 'game_translations', 'page_translations', 'faq_translations', 'category_translations'] as $tbl) {
            if (Schema::hasTable($tbl) && DB::table($tbl)->where('locale', '!=', 'en')->exists()) {
                $hasAuthoredContent = true;
                break;
            }
        }

        // 2. Check if any revisions have been authored by admins or have advanced past revision 1
        if (! $hasAuthoredContent && Schema::hasTable('entity_translation_revisions')) {
            if (DB::table('entity_translation_revisions')->where('revision_number', '>', 1)->orWhereNotNull('created_by')->exists()) {
                $hasAuthoredContent = true;
            }
        }

        // 3. Check if any new entities have been created since backfill
        if (! $hasAuthoredContent) {
            foreach (['resource_translations', 'game_translations', 'page_translations', 'faq_translations', 'category_translations'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    // Check if any English row references a revision with created_by set
                    $hasAuthoredRow = DB::table($tbl)
                        ->where('locale', 'en')
                        ->where(function ($q) {
                            $q->where('source_revision_id', '>', 1)
                                ->orWhereNull('source_revision_id');
                        })
                        ->exists();

                    if ($hasAuthoredRow) {
                        $hasAuthoredContent = true;
                        break;
                    }
                }
            }
        }

        if ($hasAuthoredContent) {
            throw new RuntimeException('Cannot rollback migration: populated translation or revision data exists. Reversing this migration would cause data loss of authored content. Use forward recovery or restore from backup.');
        }

        // Only in a pristine unedited state: remove initial seeded English rows and initial revision snapshots
        foreach (['resource_translations', 'game_translations', 'page_translations', 'faq_translations', 'category_translations'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                DB::table($tbl)->where('locale', 'en')->where('source_revision_id', 1)->delete();
            }
        }

        if (Schema::hasTable('entity_translation_revisions')) {
            DB::table('entity_translation_revisions')
                ->where('revision_number', 1)
                ->where('locale', 'en')
                ->whereNull('created_by')
                ->delete();
        }
    }
};
