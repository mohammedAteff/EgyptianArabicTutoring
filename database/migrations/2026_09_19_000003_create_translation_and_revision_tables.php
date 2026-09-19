<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Immutable English source revision history table
        Schema::create('entity_translation_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->index(); // resource, game, page, faq, category
            $table->unsignedBigInteger('entity_id')->index();
            $table->unsignedInteger('revision_number');
            $table->string('locale', 5)->default('en');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('administrators')->nullOnDelete();
            $table->dateTime('created_at')->index();

            $table->unique(['entity_type', 'entity_id', 'revision_number'], 'uq_entity_revision');
        });

        // 2. Resource translations child table
        Schema::create('resource_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, stale, archived
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('live_locale', 5)->virtualAs("CASE WHEN status IN ('published', 'stale') THEN locale ELSE NULL END");
            $table->string('draft_locale', 5)->virtualAs("CASE WHEN status = 'draft' THEN locale ELSE NULL END");
            $table->timestamps();

            $table->unique(['resource_id', 'live_locale'], 'uq_resource_trans_live');
            $table->unique(['resource_id', 'draft_locale'], 'uq_resource_trans_draft');
        });

        // 3. Game translations child table
        Schema::create('game_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, stale, archived
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('live_locale', 5)->virtualAs("CASE WHEN status IN ('published', 'stale') THEN locale ELSE NULL END");
            $table->string('draft_locale', 5)->virtualAs("CASE WHEN status = 'draft' THEN locale ELSE NULL END");
            $table->timestamps();

            $table->unique(['game_id', 'live_locale'], 'uq_game_trans_live');
            $table->unique(['game_id', 'draft_locale'], 'uq_game_trans_draft');
        });

        // 4. Page translations child table
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, stale, archived
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('live_locale', 5)->virtualAs("CASE WHEN status IN ('published', 'stale') THEN locale ELSE NULL END");
            $table->string('draft_locale', 5)->virtualAs("CASE WHEN status = 'draft' THEN locale ELSE NULL END");
            $table->timestamps();

            $table->unique(['page_id', 'live_locale'], 'uq_page_trans_live');
            $table->unique(['page_id', 'draft_locale'], 'uq_page_trans_draft');
        });

        // 5. FAQ translations child table
        Schema::create('faq_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, stale, archived
            $table->text('question');
            $table->text('answer');
            $table->dateTime('published_at')->nullable();
            $table->string('live_locale', 5)->virtualAs("CASE WHEN status IN ('published', 'stale') THEN locale ELSE NULL END");
            $table->string('draft_locale', 5)->virtualAs("CASE WHEN status = 'draft' THEN locale ELSE NULL END");
            $table->timestamps();

            $table->unique(['faq_id', 'live_locale'], 'uq_faq_trans_live');
            $table->unique(['faq_id', 'draft_locale'], 'uq_faq_trans_draft');
        });

        // 6. Category translations child table
        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('resource_categories')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, published, stale, archived
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('live_locale', 5)->virtualAs("CASE WHEN status IN ('published', 'stale') THEN locale ELSE NULL END");
            $table->string('draft_locale', 5)->virtualAs("CASE WHEN status = 'draft' THEN locale ELSE NULL END");
            $table->timestamps();

            $table->unique(['category_id', 'live_locale'], 'uq_cat_trans_live');
            $table->unique(['category_id', 'draft_locale'], 'uq_cat_trans_draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasAuthoredContent = false;
        foreach (['resource_translations', 'game_translations', 'page_translations', 'faq_translations', 'category_translations'] as $tbl) {
            if (Schema::hasTable($tbl) && DB::table($tbl)->where('locale', '!=', 'en')->exists()) {
                $hasAuthoredContent = true;
                break;
            }
        }

        if (! $hasAuthoredContent && Schema::hasTable('entity_translation_revisions')) {
            if (DB::table('entity_translation_revisions')->where('revision_number', '>', 1)->orWhereNotNull('created_by')->exists()) {
                $hasAuthoredContent = true;
            }
        }

        if ($hasAuthoredContent) {
            // Data-preservation guard: Populated translation history must not be dropped on rollback
            throw new RuntimeException('Cannot rollback migration: populated translation or revision data exists. Tables cannot be dropped without data loss.');
        }

        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('faq_translations');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('game_translations');
        Schema::dropIfExists('resource_translations');
        Schema::dropIfExists('entity_translation_revisions');
    }
};
