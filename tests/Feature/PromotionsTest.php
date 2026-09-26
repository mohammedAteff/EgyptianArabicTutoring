<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\BlogRevision;
use App\Domains\CMS\Models\Media;
use App\Domains\Marketing\Models\Promotion;
use App\Domains\Marketing\Services\PromotionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget(PromotionService::CACHE_PREFIX);
        parent::tearDown();
    }

    public function test_active_promotion_retrieval_and_time_bounded_caching(): void
    {
        $service = app(PromotionService::class);
        $service->clearCache();

        // 1. Create a currently active promotion ending in 120 seconds
        $promo = Promotion::create([
            'title' => 'Flash Sale',
            'headline' => 'Save $50 Today Only',
            'cta_text' => 'Get Deal',
            'cta_url' => '/#pricing',
            'display_type' => 'top_bar',
            'is_active' => true,
            'starts_at' => Carbon::now('UTC')->subHour(),
            'ends_at' => Carbon::now('UTC')->addSeconds(120),
            'has_countdown' => true,
        ]);

        $active = $service->getActivePromotion();
        $this->assertNotNull($active);
        $this->assertEquals($promo->id, $active->id);

        // Transition TTL should be <= 120s
        $secondsUntilTransition = $service->getSecondsUntilNextTransition();
        $this->assertNotNull($secondsUntilTransition);
        $this->assertLessThanOrEqual(120, $secondsUntilTransition);
    }

    public function test_expired_or_future_promotions_are_not_active(): void
    {
        $service = app(PromotionService::class);
        $service->clearCache();

        // Expired promo
        Promotion::create([
            'title' => 'Expired Promo',
            'headline' => 'Expired Headline',
            'cta_text' => 'Click',
            'cta_url' => '/test',
            'display_type' => 'top_bar',
            'is_active' => true,
            'starts_at' => Carbon::now('UTC')->subDays(2),
            'ends_at' => Carbon::now('UTC')->subMinute(),
        ]);

        // Future promo
        Promotion::create([
            'title' => 'Future Promo',
            'headline' => 'Future Headline',
            'cta_text' => 'Click',
            'cta_url' => '/test',
            'display_type' => 'floating_modal',
            'is_active' => true,
            'starts_at' => Carbon::now('UTC')->addHour(),
            'ends_at' => Carbon::now('UTC')->addDays(2),
        ]);

        $active = $service->getActivePromotion();
        $this->assertNull($active);
    }

    public function test_cairo_local_time_and_utc_instant_conversion(): void
    {
        $service = app(PromotionService::class);

        // Cairo is UTC+2 or UTC+3 depending on DST
        $cairoString = '2026-06-15 14:30:00';
        $utc = $service->cairoToUtc($cairoString);

        $this->assertNotNull($utc);
        $this->assertEquals('UTC', $utc->timezoneName);

        $backToCairo = $service->utcToCairo($utc);
        $this->assertEquals('Africa/Cairo', $backToCairo->timezoneName);
        $this->assertEquals('2026-06-15 14:30:00', $backToCairo->format('Y-m-d H:i:s'));
    }

    public function test_admin_can_crud_promotions(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin_promo@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 1. Store
        $response = $this->actingAs($admin)
            ->post(route('admin.promotions.store'), [
                'title' => 'Summer Camp 2026',
                'headline' => 'Accelerate your fluency this summer',
                'subheadline' => 'Special 1-on-1 intensive',
                'cta_text' => 'Enroll Now',
                'cta_url' => '/#curriculum',
                'display_type' => 'top_bar',
                'is_active' => '1',
                'starts_at' => '2026-07-01T10:00',
                'ends_at' => '2026-07-31T23:59',
                'has_countdown' => '1',
            ]);

        $response->assertRedirect(route('admin.promotions.index'));
        $this->assertDatabaseHas('promotions', [
            'title' => 'Summer Camp 2026',
            'display_type' => 'top_bar',
            'is_active' => true,
        ]);

        $promo = Promotion::where('title', 'Summer Camp 2026')->first();

        // 2. Edit & Update
        $this->actingAs($admin)
            ->get(route('admin.promotions.edit', $promo))
            ->assertOk()
            ->assertSee('Summer Camp 2026');

        $this->actingAs($admin)
            ->put(route('admin.promotions.update', $promo), [
                'title' => 'Summer Camp 2026 Updated',
                'headline' => 'Accelerate your fluency this summer (Updated)',
                'cta_text' => 'Enroll Now',
                'cta_url' => '/#curriculum',
                'display_type' => 'floating_modal',
                'is_active' => '1',
                'starts_at' => '2026-07-01T10:00',
                'ends_at' => '2026-07-31T23:59',
            ])
            ->assertRedirect(route('admin.promotions.index'));

        $this->assertEquals('Summer Camp 2026 Updated', $promo->fresh()->title);
        $this->assertEquals('floating_modal', $promo->fresh()->display_type);

        // 3. Toggle
        $this->actingAs($admin)
            ->post(route('admin.promotions.toggle', $promo))
            ->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);

        // 4. Destroy
        $this->actingAs($admin)
            ->delete(route('admin.promotions.destroy', $promo))
            ->assertRedirect(route('admin.promotions.index'));
        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
    }

    public function test_live_preview_access_control(): void
    {
        $promo = Promotion::create([
            'title' => 'Secret Launch',
            'headline' => 'Unreleased Special',
            'cta_text' => 'Join',
            'cta_url' => '/#pricing',
            'display_type' => 'top_bar',
            'is_active' => false,
        ]);

        // 1. Guest redirected
        $this->get(route('admin.promotions.preview', $promo))
            ->assertRedirect();

        // 2. Assistant denied (HTTP 403 via EnsureAdminPreviewAccess)
        $assistant = Administrator::create([
            'name' => 'Assistant User',
            'email' => 'assistant_promo@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'assistant',
            'is_active' => true,
        ]);
        $this->actingAs($assistant)
            ->get(route('admin.promotions.preview', $promo))
            ->assertStatus(403);

        // 3. Admin authorized (HTTP 200)
        $admin = Administrator::create([
            'name' => 'Admin User 2',
            'email' => 'admin_promo2@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin)
            ->get(route('admin.promotions.preview', $promo))
            ->assertOk()
            ->assertSee('Secret Launch')
            ->assertSee('Unreleased Special');
    }

    public function test_public_layout_renders_active_promotion(): void
    {
        $service = app(PromotionService::class);
        $service->clearCache();

        Promotion::create([
            'title' => 'Live Banner',
            'headline' => 'Visible on Homepage',
            'cta_text' => 'Click Here',
            'cta_url' => '/#pricing',
            'display_type' => 'top_bar',
            'is_active' => true,
            'starts_at' => Carbon::now('UTC')->subDay(),
            'ends_at' => Carbon::now('UTC')->addDay(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Visible on Homepage')
            ->assertSee('Click Here');
    }

    public function test_promotion_rejects_executable_links_and_stores_uploaded_image_with_a_safe_extension(): void
    {
        Storage::fake('public');
        $admin = Administrator::create([
            'name' => 'Promotion Editor',
            'email' => 'promo_security@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $payload = [
            'title' => 'Safe Offer',
            'headline' => 'A safe offer',
            'cta_text' => 'Open',
            'display_type' => 'top_bar',
            'is_active' => '1',
        ];

        $this->actingAs($admin, 'web')
            ->post(route('admin.promotions.store'), $payload + ['cta_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('cta_url');
        $this->post(route('admin.promotions.store'), $payload + ['cta_url' => '//attacker.example'])
            ->assertSessionHasErrors('cta_url');
        $this->assertDatabaseCount('promotions', 0);

        $this->post(route('admin.promotions.store'), $payload + [
            'cta_url' => '/pricing',
            'banner_image' => UploadedFile::fake()->image('payload.php'),
        ])->assertSessionHasErrors('banner_image');
        $this->assertDatabaseCount('promotions', 0);

        $this->post(route('admin.promotions.store'), $payload + [
            'cta_url' => '/pricing',
            'banner_image' => UploadedFile::fake()->image('safe.png'),
        ])->assertRedirect(route('admin.promotions.index'));

        $path = Promotion::query()->firstOrFail()->banner_image_path;
        $this->assertStringStartsWith('promotions/', $path);
        $this->assertMatchesRegularExpression('/\.(?:png|jpe?g|webp)$/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media', ['path' => $path, 'disk' => 'public']);
    }

    public function test_public_promotion_rejects_legacy_unsafe_links_and_prefixes_local_links_with_the_site_base_path(): void
    {
        $promotion = Promotion::create([
            'title' => 'Unsafe legacy offer',
            'headline' => 'Unsafe legacy headline',
            'cta_text' => 'Open',
            'cta_url' => 'javascript:alert(1)',
            'display_type' => 'top_bar',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Unsafe legacy headline');

        $promotion->update(['cta_url' => '/pricing']);
        app(PromotionService::class)->clearCache();
        URL::forceRootUrl('https://example.test/arabictutor');
        URL::forceScheme('https');

        $html = Blade::render('<x-promotional-banner :promotion="$promotion" />', ['promotion' => $promotion]);
        $this->assertStringContainsString('href="https://example.test/arabictutor/pricing"', $html);
    }

    public function test_media_library_cannot_delete_images_used_by_promotions_or_blog_revisions(): void
    {
        Storage::fake('public');
        $admin = Administrator::create([
            'name' => 'Media Editor',
            'email' => 'promo_media@example.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $path = 'media/shared-banner.png';
        Storage::disk('public')->put($path, 'image');
        $media = Media::create([
            'filename' => 'shared-banner.png',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/png',
            'file_size' => 5,
        ]);
        $promotion = Promotion::create([
            'title' => 'Shared banner promotion',
            'headline' => 'Shared banner',
            'cta_text' => 'Book',
            'cta_url' => '/booking',
            'display_type' => 'top_bar',
            'banner_image_path' => $path,
        ]);

        $this->actingAs($admin, 'web')->delete(route('admin.media.destroy', $media))
            ->assertSessionHas('error');
        Storage::disk('public')->assertExists($path);
        $this->assertTrue(Media::isPathReferenced($path));

        $promotion->delete();
        $blog = Blog::factory()->create(['featured_image_path' => $path]);
        $this->delete(route('admin.media.destroy', $media))->assertSessionHas('error');

        $blog->update(['featured_image_path' => null]);
        $revision = BlogRevision::create([
            'blog_id' => $blog->id,
            'snapshot' => ['featured_image_path' => $path],
            'revised_by' => $admin->id,
            'created_at' => now(),
        ]);
        $this->delete(route('admin.media.destroy', $media))->assertSessionHas('error');
        Storage::disk('public')->assertExists($path);

        $revision->delete();
        $this->delete(route('admin.media.destroy', $media))->assertSessionMissing('error');
        Storage::disk('public')->assertMissing($path);
    }
}
