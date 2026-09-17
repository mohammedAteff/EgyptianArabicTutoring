<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Faq;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CmsDraftAndPreviewMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected ResourceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@egyptianarabic.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
        ]);

        $this->category = ResourceCategory::create([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    public function test_resource_draft_update_preserves_live_version_and_saves_content_revision(): void
    {
        // 1. Create published resource
        $resource = Resource::create([
            'title' => 'Original Published Grammar Guide',
            'slug' => 'original-grammar-guide',
            'category_id' => $this->category->id,
            'short_description' => 'Original public description',
            'file_type' => 'pdf',
            'file_path' => 'resources/test.pdf',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        // 2. Submit draft update via admin
        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.resources.update', $resource), [
                'action' => 'draft',
                'title' => 'Updated Draft Title Not Yet Public',
                'slug' => 'original-grammar-guide',
                'category_id' => $this->category->id,
                'short_description' => 'Draft description under review',
                'file_type' => 'pdf',
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('admin.resources.edit', $resource));

        // 3. Live resource remains published and unchanged
        $resource->refresh();
        $this->assertSame('Original Published Grammar Guide', $resource->title);
        $this->assertSame('Original public description', $resource->short_description);
        $this->assertSame('published', $resource->status);

        // 4. Draft revision is saved
        $draftRevision = $resource->revisions()->where('status', 'draft')->latest('id')->first();
        $this->assertNotNull($draftRevision);
        $this->assertSame('Updated Draft Title Not Yet Public', $draftRevision->title);

        // 5. Public show route shows the live version
        $publicResponse = $this->get(route('resources.show', $resource->slug));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Original Published Grammar Guide');
        $publicResponse->assertDontSee('Updated Draft Title Not Yet Public');

        // 6. Authenticated admin preview shows draft version
        $previewResponse = $this->actingAs($this->admin, 'web')
            ->get(route('resources.preview', $resource->slug));
        $previewResponse->assertOk();
        $previewResponse->assertSee('Updated Draft Title Not Yet Public');
        $previewResponse->assertSee('[PREVIEW]');
    }

    public function test_game_draft_update_preserves_live_version_and_authenticated_preview(): void
    {
        // 1. Create available game
        $game = Game::create([
            'title' => 'Street Market Vocab',
            'slug' => 'street-market-vocab',
            'description' => 'Original market phrase quiz',
            'badge' => 'Phrases',
            'status' => 'available',
            'sort_order' => 1,
        ]);

        // 2. Submit draft update
        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.games.update', $game), [
                'action' => 'draft',
                'title' => 'Updated Market Vocab Draft',
                'slug' => 'street-market-vocab',
                'description' => 'New draft question set',
                'badge' => 'Intermediate',
                'status' => 'draft',
                'sort_order' => 1,
            ]);

        $response->assertRedirect(route('admin.games.edit', $game));

        // 3. Live game remains available and unchanged
        $game->refresh();
        $this->assertSame('Street Market Vocab', $game->title);
        $this->assertSame('Original market phrase quiz', $game->description);
        $this->assertSame('available', $game->status);

        // 4. Draft revision is saved
        $draftRevision = $game->revisions()->where('status', 'draft')->latest('id')->first();
        $this->assertNotNull($draftRevision);
        $this->assertSame('Updated Market Vocab Draft', $draftRevision->title);

        // 5. Authenticated preview shows draft content
        $previewResponse = $this->actingAs($this->admin, 'web')
            ->get(route('games.preview', $game->slug));
        $previewResponse->assertOk();
        $previewResponse->assertSee('Updated Market Vocab Draft');
        $previewResponse->assertSee('[PREVIEW]');
    }

    public function test_faq_preview_requires_authentication_and_renders_inactive_faqs(): void
    {
        // 1. Create active and inactive FAQs
        Faq::create([
            'question' => 'Public Active Question?',
            'answer' => 'Public Answer.',
            'sort_order' => 1,
            'active' => true,
        ]);

        Faq::create([
            'question' => 'Draft Hidden Question?',
            'answer' => 'Hidden Answer.',
            'sort_order' => 2,
            'active' => false,
        ]);

        // 2. Public /faq route shows only active FAQ
        $publicResponse = $this->get(route('faq'));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Public Active Question?');
        $publicResponse->assertDontSee('Draft Hidden Question?');

        // 3. Unauthenticated guest accessing /faq/preview is denied with 403
        $guestPreview = $this->get(route('faq.preview'));
        $guestPreview->assertForbidden();

        // 4. Authenticated admin accessing /faq/preview sees both active and draft FAQs
        $adminPreview = $this->actingAs($this->admin, 'web')
            ->get(route('faq.preview'));
        $adminPreview->assertOk();
        $adminPreview->assertSee('Public Active Question?');
        $adminPreview->assertSee('Draft Hidden Question?');
        $adminPreview->assertSee('Administrator Preview Mode');
    }

    public function test_preview_routes_reject_unauthenticated_guests_even_with_fake_parameters(): void
    {
        $resource = Resource::create([
            'title' => 'Grammar Cheatsheet',
            'slug' => 'grammar-cheatsheet',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'draft',
        ]);

        $game = Game::create([
            'title' => 'Number Practice',
            'slug' => 'number-practice',
            'status' => 'draft',
        ]);

        $this->get(route('resources.preview', $resource->slug))->assertForbidden();
        $this->get(route('games.preview', $game->slug))->assertForbidden();
        $this->get(route('faq.preview'))->assertForbidden();
        $this->get(route('home.preview'))->assertForbidden();
        $this->get(route('about.preview'))->assertForbidden();
    }
}
