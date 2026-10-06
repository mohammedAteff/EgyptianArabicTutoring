<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\Games\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDrawerAndSocialAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
        ]);
    }

    /**
     * Section 32 & 34.6: Verify admin mobile drawer accessibility markup, ARIA roles, focus management, and scroll lock.
     */
    public function test_admin_mobile_drawer_attributes_and_accessibility_markup(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/dashboard');

        $response->assertStatus(200);

        // Viewport meta
        $response->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1">', false);

        // Drawer dialog container
        $response->assertSee('id="admin-sidebar"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-label="Admin Navigation"', false);

        // Hamburger button
        $response->assertSee('id="admin-menu-toggle"', false);
        $response->assertSee('aria-controls="admin-sidebar"', false);
        $response->assertSee(':aria-expanded="mobileSidebarOpen ? \'true\' : \'false\'"', false);
        $response->assertSee('aria-label="Open Navigation Menu"', false);

        // Close button inside drawer
        $response->assertSee('aria-label="Close Navigation Menu"', false);

        // Focus trap, Escape handling, and scroll lock in adminMobileNav
        $response->assertSee('function adminMobileNav()', false);
        $response->assertSee("document.body.style.overflow = 'hidden'", false);
        $response->assertSee('e.key === \'Escape\'', false);
        $response->assertSee('e.key === \'Tab\'', false);
        $response->assertSee('this.getFocusableElements(sidebar)', false);
        $response->assertSee('@click="closeSidebar()"', false);

        // Desktop layout reserves the fixed sidebar width for the entire content column,
        // including the topbar, so the navigation cannot cover the search/header area.
        $response->assertSee('class="flex-1 flex flex-col min-w-0 overflow-hidden md:ml-64"', false);
        $response->assertSee('class="ml-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8"', false);

        // The security audit table is restricted to Super Admins and remains scrollable.
        $this->admin->update(['role' => 'super_admin']);
        $this->actingAs($this->admin, 'web')->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    /**
     * Section 33: Verify admin and preview routes emit X-Robots-Tag: noindex, nofollow, noarchive.
     */
    public function test_admin_and_preview_routes_emit_x_robots_tag_noindex(): void
    {
        // Public admin login
        $loginRes = $this->get('/admin/login');
        $loginRes->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Forgot password
        $forgotRes = $this->get('/admin/forgot-password');
        $forgotRes->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Authenticated admin dashboard
        $dashRes = $this->actingAs($this->admin, 'web')->get('/admin/dashboard');
        $dashRes->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Preview route
        $previewRes = $this->actingAs($this->admin, 'web')->get('/preview/home');
        $previewRes->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /**
     * Section 31: Verify public footer social links use Icon + Visible Label, aria-hidden SVGs, and non-blocking tracking.
     */
    public function test_public_footer_social_links_have_icon_and_visible_label(): void
    {
        // Seed enabled social links
        SocialLink::create([
            'platform' => 'instagram',
            'url_or_phone' => 'https://instagram.com/arabictutor',
            'label' => 'Instagram',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        SocialLink::create([
            'platform' => 'tiktok',
            'url_or_phone' => 'https://tiktok.com/@arabictutor',
            'label' => 'TikTok',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        SocialLink::create([
            'platform' => 'youtube',
            'url_or_phone' => 'https://youtube.com/@arabictutor',
            'label' => 'YouTube',
            'enabled' => true,
            'sort_order' => 3,
        ]);

        SocialLink::create([
            'platform' => 'telegram',
            'url_or_phone' => '@arabictutor',
            'label' => 'Telegram Channel',
            'enabled' => true,
            'sort_order' => 4,
        ]);

        SocialLink::create([
            'platform' => 'whatsapp',
            'url_or_phone' => '+201234567890',
            'label' => 'WhatsApp Chat',
            'enabled' => true,
            'sort_order' => 5,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Assert visible text labels
        $response->assertSee('Instagram');
        $response->assertSee('TikTok');
        $response->assertSee('YouTube');
        $response->assertSee('Telegram Channel');
        $response->assertSee('WhatsApp Chat');

        // Assert accessible aria-label attributes
        $response->assertSee('aria-label="Visit our Instagram page"', false);
        $response->assertSee('aria-label="Visit our TikTok page"', false);
        $response->assertSee('aria-label="Visit our YouTube page"', false);
        $response->assertSee('aria-label="Visit our Telegram Channel page"', false);
        $response->assertSee('aria-label="Visit our WhatsApp Chat page"', false);

        // Assert decorative inline SVGs with aria-hidden="true"
        $response->assertSee('aria-hidden="true"', false);

        // Assert non-blocking analytics script
        $response->assertSee('window.vaTrack', false);
        $response->assertSee('navigator.sendBeacon', false);
        $response->assertSee('keepalive: true', false);
        $response->assertDontSee('preventDefault', false);
    }

    /**
     * Section 33: Verify public pages contain zero links to admin portal.
     */
    public function test_public_pages_contain_no_admin_panel_links(): void
    {
        $publicRoutes = ['/', '/about', '/faq', '/resources', '/games', '/booking'];

        foreach ($publicRoutes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);

            $content = $response->getContent();
            $this->assertStringNotContainsString('href="/admin"', $content, "Route {$route} contains href='/admin'");
            $this->assertStringNotContainsString('href="http://localhost/admin', $content, "Route {$route} contains admin link");
            $this->assertStringNotContainsString('Tutor Admin Portal', $content, "Route {$route} contains Tutor Admin Portal text");
            $this->assertStringNotContainsString('Tutor Panel', $content, "Route {$route} contains Tutor Panel text");
        }
    }

    /**
     * Section 29: Verify embedded Arabic learning content has bidirectional isolation.
     */
    public function test_embedded_arabic_has_bidirectional_isolation(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Public page must be LTR
        $response->assertSee('dir="ltr"', false);
        $response->assertDontSee('dir="rtl" class="h-full scroll-smooth"', false);

        // Embedded learning content wrapped in bdi with dir="rtl" and lang="ar"
        $response->assertSee('<bdi dir="rtl" lang="ar"', false);
        $response->assertSee('اتعلم مصري صح', false);
        $response->assertSee('إزيك؟ عامل إيه؟', false);

        // Game page
        $game = Game::create([
            'slug' => 'street-phrases-challenge',
            'title' => 'Street Phrases Challenge',
            'description' => 'Test your Arabic street phrases',
            'game_type' => 'flashcard',
            'is_published' => true,
        ]);

        $gameRes = $this->get("/games/{$game->slug}");
        $gameRes->assertStatus(200);
        $gameRes->assertSee('<bdi dir="rtl" lang="ar"', false);
    }

    /**
     * Section 20 & 26: Verify desktop nav and mobile drawer have language switcher with active indicators.
     */
    public function test_public_navigation_contains_language_switcher(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // English is active
        $response->assertSee('aria-label="Language selector"', false);
        $response->assertSee('aria-current="true"', false);
        $response->assertSee('EN', false);
        $response->assertSee('FR', false);
        $response->assertSee('DE', false);

        // Localized URLs linked
        $response->assertSee('/fr', false);
        $response->assertSee('/de', false);

        // Switch to French
        $frResponse = $this->get('/fr');
        $frResponse->assertStatus(200);
        $frResponse->assertSee('FR', false);
    }
}
