<?php

namespace Tests\Feature;

use App\Domains\CMS\Services\TranslationService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Resources\Models\ResourceTranslation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class CmsTranslationAndRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected TranslationService $translationService;

    protected ResourceCategory $category;

    protected Resource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationService = app(TranslationService::class);

        $this->category = ResourceCategory::create([
            'name' => 'Grammar Guides',
            'slug' => 'grammar-guides',
            'active' => true,
        ]);

        $this->resource = Resource::create([
            'category_id' => $this->category->id,
            'title' => 'Egyptian Arabic Starter Guide',
            'slug' => 'starter-guide',
            'short_description' => 'A comprehensive introduction to Egyptian Arabic.',
            'full_description' => 'Detailed beginner lessons, audio, and vocabulary tables.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_initial_migration_creates_revision_one(): void
    {
        // Re-run migration backfill logic on the newly created test resource
        $rev = $this->translationService->updateEnglishSource($this->resource, [
            'title' => $this->resource->title,
            'short_description' => $this->resource->short_description,
            'full_description' => $this->resource->full_description,
        ]);

        $this->assertEquals(1, $rev->revision_number);
        $this->assertEquals('en', $rev->locale);
        $this->assertEquals('Egyptian Arabic Starter Guide', $rev->title);

        $enTrans = $this->resource->liveTranslation('en');
        $this->assertNotNull($enTrans);
        $this->assertEquals(1, $enTrans->source_revision_id);
        $this->assertEquals('published', $enTrans->status);
    }

    public function test_source_revision_only_changes_for_translatable_content(): void
    {
        // Initial Revision 1
        $rev1 = $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'Original Title',
            'short_description' => 'Original Short Desc',
            'full_description' => 'Original Full Desc',
        ]);
        $this->assertEquals(1, $rev1->revision_number);

        // Updating with identical translatable content must NOT create a new revision
        $revSame = $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'Original Title',
            'short_description' => 'Original Short Desc',
            'full_description' => 'Original Full Desc',
        ]);
        $this->assertEquals(1, $revSame->revision_number);

        // Updating with changed translatable content increments to Revision 2
        $rev2 = $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'Updated Title Version 2',
            'short_description' => 'Updated Short Desc',
            'full_description' => 'Original Full Desc',
        ]);
        $this->assertEquals(2, $rev2->revision_number);
    }

    public function test_stale_translation_remains_publicly_visible(): void
    {
        // 1. Initial English Revision 1
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'English Rev 1 Title',
            'short_description' => 'English Rev 1 Description',
        ]);

        // 2. Publish French Translation based on Rev 1
        $this->translationService->saveDraft($this->resource, 'fr', [
            'title' => 'Titre Français Rev 1',
            'short_description' => 'Description Française Rev 1',
        ]);
        $this->translationService->publishTranslation($this->resource, 'fr');

        $frTrans = $this->resource->liveTranslation('fr');
        $this->assertEquals('published', $frTrans->status);

        // 3. Advance English to Revision 2
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'English Rev 2 Title Changed',
            'short_description' => 'English Rev 2 Description Changed',
        ]);

        // 4. Verify French becomes stale
        $frTrans->refresh();
        $this->assertEquals('stale', $frTrans->status);

        // 5. Section 27: Stale translation remains publicly visible and is NOT a fallback state
        $resolution = $this->resource->resolveTranslation('fr');
        $this->assertFalse($resolution['is_fallback']);
        $this->assertTrue($resolution['is_stale']);
        $this->assertEquals('fr', $resolution['effective_locale']);
        $this->assertEquals('Titre Français Rev 1', $resolution['translation']->title);
    }

    public function test_draft_reconciliation_flagged_on_new_source_revision(): void
    {
        // 1. English Rev 1
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'English Title 1',
            'short_description' => 'Desc 1',
        ]);

        // 2. French draft created against Rev 1
        $this->translationService->saveDraft($this->resource, 'fr', [
            'title' => 'Brouillon Français',
            'short_description' => 'Desc Fr',
        ]);

        $this->assertFalse($this->translationService->draftRequiresReconciliation($this->resource, 'fr'));

        // 3. English advances to Rev 2
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'English Title 2',
            'short_description' => 'Desc 2',
        ]);

        // 4. Draft must now be flagged as requiring reconciliation against current revision
        $this->assertTrue($this->translationService->draftRequiresReconciliation($this->resource, 'fr'));

        // Draft content is preserved intact
        $draft = $this->resource->draftTranslation('fr');
        $this->assertEquals('Brouillon Français', $draft->title);
        $this->assertEquals(1, $draft->source_revision_id);
    }

    public function test_draft_save_does_not_publish(): void
    {
        // English Rev 1
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'Live English Resource',
            'short_description' => 'Live English Description',
        ]);

        // Save French draft
        $draft = $this->translationService->saveDraft($this->resource, 'fr', [
            'title' => 'Titre en cours de rédaction',
            'short_description' => 'Description en cours',
        ]);

        $this->assertEquals('draft', $draft->status);

        // Public resolution must fall back to English because French is draft-only
        $resolution = $this->resource->resolveTranslation('fr');
        $this->assertTrue($resolution['is_fallback']);
        $this->assertEquals('en', $resolution['effective_locale']);
        $this->assertEquals('Live English Resource', $resolution['translation']->title);
    }

    public function test_explicit_publish_transitions_correctly(): void
    {
        // English Rev 1
        $this->translationService->updateEnglishSource($this->resource, [
            'title' => 'Live English Resource',
            'short_description' => 'Live English Description',
        ]);

        // Save and publish French
        $this->translationService->saveDraft($this->resource, 'fr', [
            'title' => 'Version 1 Publiée',
            'short_description' => 'Description V1',
        ]);
        $this->translationService->publishTranslation($this->resource, 'fr');

        $v1 = $this->resource->liveTranslation('fr');
        $this->assertEquals('published', $v1->status);
        $this->assertEquals('Version 1 Publiée', $v1->title);

        // Save a new draft while V1 is live
        $this->translationService->saveDraft($this->resource, 'fr', [
            'title' => 'Version 2 Nouveau Brouillon',
            'short_description' => 'Description V2',
        ]);

        // V1 is still the live translation!
        $this->assertEquals('Version 1 Publiée', $this->resource->liveTranslation('fr')->title);

        // Explicitly publish V2
        $this->translationService->publishTranslation($this->resource, 'fr');

        // V1 is now archived
        $v1->refresh();
        $this->assertEquals('archived', $v1->status);

        // V2 is now published
        $newLive = $this->resource->liveTranslation('fr');
        $this->assertEquals('Version 2 Nouveau Brouillon', $newLive->title);
        $this->assertEquals('published', $newLive->status);
    }

    public function test_missing_static_translation_falls_back_to_english(): void
    {
        // cta.start is defined in en.json as "Start Now", but omitted from de.json
        App::setLocale('de');

        $translated = __('cta.start');

        $this->assertEquals('Start Now', $translated, 'Missing key in German must fall back to English translation');
        $this->assertNotEquals('cta.start', $translated, 'Raw translation key must never be exposed to user');
    }

    public function test_database_unique_constraints_enforce_single_live_and_single_draft(): void
    {
        // Attempting to create two draft translations for the same resource and locale must throw a unique constraint violation
        ResourceTranslation::create([
            'resource_id' => $this->resource->id,
            'locale' => 'de',
            'status' => 'draft',
            'title' => 'Draft 1',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ResourceTranslation::create([
            'resource_id' => $this->resource->id,
            'locale' => 'de',
            'status' => 'draft',
            'title' => 'Draft 2 Duplicate',
        ]);
    }
}
