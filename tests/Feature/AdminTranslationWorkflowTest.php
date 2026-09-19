<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTranslationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
        ]);
    }

    public function test_resource_english_edit_creates_new_revision_and_stales_french_translation(): void
    {
        $this->actingAs($this->admin, 'web');

        $category = ResourceCategory::create([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'active' => true,
        ]);

        // 1. Create Resource through controller
        $resResponse = $this->post(route('admin.resources.store'), [
            'title' => 'Egyptian Verbs 101',
            'slug' => 'egyptian-verbs-101',
            'category_id' => $category->id,
            'short_description' => 'Initial verb guide',
            'file_type' => 'pdf',
            'status' => 'published',
        ]);
        $resResponse->assertRedirect(route('admin.resources.index'));

        $resource = Resource::where('slug', 'egyptian-verbs-101')->firstOrFail();
        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'resource',
            'entity_id' => $resource->id,
            'revision_number' => 1,
            'title' => 'Egyptian Verbs 101',
        ]);

        // 2. Save and publish a French translation
        $this->post(route('admin.translations.publish', ['entityType' => 'resource', 'id' => $resource->id, 'locale' => 'fr']), [
            'title' => 'Les verbes égyptiens 101',
            'short_description' => 'Guide initial des verbes',
        ])->assertRedirect();

        $this->assertDatabaseHas('resource_translations', [
            'resource_id' => $resource->id,
            'locale' => 'fr',
            'status' => 'published',
            'title' => 'Les verbes égyptiens 101',
            'source_revision_id' => 1,
        ]);

        // 3. Perform a no-op edit (or operational edit: change sort_order only)
        $this->put(route('admin.resources.update', $resource), [
            'title' => 'Egyptian Verbs 101',
            'slug' => 'egyptian-verbs-101',
            'category_id' => $category->id,
            'short_description' => 'Initial verb guide',
            'file_type' => 'pdf',
            'status' => 'published',
            'sort_order' => 5,
        ]);

        // Assert: NO new revision 2 created on no-op
        $this->assertDatabaseMissing('entity_translation_revisions', [
            'entity_type' => 'resource',
            'entity_id' => $resource->id,
            'revision_number' => 2,
        ]);
        // French translation remains published (not stale)
        $this->assertDatabaseHas('resource_translations', [
            'resource_id' => $resource->id,
            'locale' => 'fr',
            'status' => 'published',
        ]);

        // 4. Perform an actual translatable change
        $this->put(route('admin.resources.update', $resource), [
            'title' => 'Egyptian Verbs 101 - 2nd Edition',
            'slug' => 'egyptian-verbs-101',
            'category_id' => $category->id,
            'short_description' => 'Completely revised verb guide',
            'file_type' => 'pdf',
            'status' => 'published',
            'sort_order' => 5,
        ]);

        // Assert: Revision 2 was created
        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'resource',
            'entity_id' => $resource->id,
            'revision_number' => 2,
            'title' => 'Egyptian Verbs 101 - 2nd Edition',
        ]);

        // Assert: French translation marked STALE
        $this->assertDatabaseHas('resource_translations', [
            'resource_id' => $resource->id,
            'locale' => 'fr',
            'status' => 'stale',
            'source_revision_id' => 1,
        ]);
    }

    public function test_draft_save_reconciliation_and_atomic_publish_workflow(): void
    {
        $this->actingAs($this->admin, 'web');

        $game = Game::create([
            'title' => 'Egyptian Numbers Drill',
            'slug' => 'numbers-drill',
            'description' => 'Count from 1 to 100 in spoken Egyptian Arabic.',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        // Seed initial English revision 1
        app(TranslationService::class)->updateEnglishSource($game, [
            'title' => $game->title,
            'description' => $game->description,
        ], $this->admin->id);

        // 1. Save French draft
        $this->post(route('admin.translations.save-draft', ['entityType' => 'game', 'id' => $game->id, 'locale' => 'fr']), [
            'title' => 'Jeu des chiffres égyptiens',
            'description' => 'Comptez de 1 à 100 en arabe égyptien parlé.',
        ])->assertRedirect();

        // Draft exists with status draft and source_revision_id = 1
        $this->assertDatabaseHas('game_translations', [
            'game_id' => $game->id,
            'locale' => 'fr',
            'status' => 'draft',
            'source_revision_id' => 1,
        ]);

        // Public resolution still shows fallback because draft is not published
        $resolved = $game->resolveTranslation('fr');
        $this->assertTrue($resolved['is_fallback']);

        // 2. English source advances to revision 2
        $this->put(route('admin.games.update', $game), [
            'title' => 'Egyptian Numbers Drill — Advanced Edition',
            'description' => 'Count from 1 to 1,000 with Egyptian slang terms.',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'game',
            'entity_id' => $game->id,
            'revision_number' => 2,
        ]);

        // Draft still has source_revision_id = 1, so it requires reconciliation
        $this->assertTrue(app(TranslationService::class)->draftRequiresReconciliation($game, 'fr'));

        // Reconciliation view renders both source snapshots
        $reconcileView = $this->get(route('admin.translations.reconcile', ['entityType' => 'game', 'id' => $game->id, 'locale' => 'fr']));
        $reconcileView->assertOk();
        $reconcileView->assertSee('Source Revision #1');
        $reconcileView->assertSee('Current English Revision #2');

        // Unreconciled publish attempt MUST FAIL with error feedback
        $this->post(route('admin.translations.publish', ['entityType' => 'game', 'id' => $game->id, 'locale' => 'fr']))
            ->assertSessionHas('error');
        $this->assertEquals('draft', $game->fresh()->draftTranslation('fr')->status);
        $this->assertEquals(1, $game->fresh()->draftTranslation('fr')->source_revision_id);

        // Reconciled save advances draft's source reference to revision 2
        $this->post(route('admin.translations.save-draft', ['entityType' => 'game', 'id' => $game->id, 'locale' => 'fr']), [
            'title' => 'Jeu des chiffres égyptiens — Édition avancée',
            'description' => 'Comptez de 1 à 1 000 avec expressions populaires.',
            'reconcile_to_revision' => 2,
        ])->assertRedirect();

        $this->assertEquals(2, $game->fresh()->draftTranslation('fr')->source_revision_id);

        // 3. Explicit Publish action updates draft to published, archives prior live, and stamps revision 2
        $this->post(route('admin.translations.publish', ['entityType' => 'game', 'id' => $game->id, 'locale' => 'fr']))
            ->assertRedirect();

        $this->assertDatabaseHas('game_translations', [
            'game_id' => $game->id,
            'locale' => 'fr',
            'status' => 'published',
            'source_revision_id' => 2,
            'title' => 'Jeu des chiffres égyptiens — Édition avancée',
        ]);

        // Now public resolution finds the published French translation
        $resolvedAfterPublish = $game->fresh()->resolveTranslation('fr');
        $this->assertFalse($resolvedAfterPublish['is_fallback']);
        $this->assertSame('Jeu des chiffres égyptiens — Édition avancée', $resolvedAfterPublish['translation']->title);
    }

    public function test_page_faq_and_category_translation_workflows(): void
    {
        $this->actingAs($this->admin, 'web');

        // Page
        $page = Page::create([
            'title' => 'About Our Tutor',
            'slug' => 'about-tutor',
            'content' => 'Original English biography.',
            'status' => 'published',
        ]);
        app(TranslationService::class)->updateEnglishSource($page, [
            'title' => $page->title,
            'content' => $page->content,
        ], $this->admin->id);

        $this->post(route('admin.translations.publish', ['entityType' => 'page', 'id' => $page->id, 'locale' => 'de']), [
            'title' => 'Über unseren Lehrer',
            'content' => 'Deutsche Biografie.',
        ])->assertRedirect();

        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale' => 'de',
            'status' => 'published',
        ]);

        // English page edit marks DE stale
        $this->put(route('admin.pages.update', $page), [
            'title' => 'About Our Lead Tutor',
            'slug' => 'about-tutor',
            'content' => 'Updated English biography.',
            'status' => 'published',
        ]);

        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale' => 'de',
            'status' => 'stale',
        ]);

        // FAQ
        $faq = Faq::create([
            'question' => 'How are lessons conducted?',
            'answer' => 'Via Google Meet.',
            'active' => true,
        ]);
        app(TranslationService::class)->updateEnglishSource($faq, [
            'question' => $faq->question,
            'answer' => $faq->answer,
        ], $this->admin->id);

        $this->post(route('admin.translations.publish', ['entityType' => 'faq', 'id' => $faq->id, 'locale' => 'fr']), [
            'question' => 'Comment se déroulent les cours ?',
            'answer' => 'Via Google Meet.',
        ])->assertRedirect();

        $this->assertDatabaseHas('faq_translations', [
            'faq_id' => $faq->id,
            'locale' => 'fr',
            'status' => 'published',
        ]);

        // Category
        $cat = ResourceCategory::create([
            'name' => 'Audio Drills',
            'slug' => 'audio-drills',
            'active' => true,
        ]);
        app(TranslationService::class)->updateEnglishSource($cat, [
            'name' => $cat->name,
        ], $this->admin->id);

        $this->post(route('admin.translations.publish', ['entityType' => 'category', 'id' => $cat->id, 'locale' => 'de']), [
            'name' => 'Audio-Übungen',
        ])->assertRedirect();

        $this->assertDatabaseHas('category_translations', [
            'category_id' => $cat->id,
            'locale' => 'de',
            'status' => 'published',
        ]);
    }
}
