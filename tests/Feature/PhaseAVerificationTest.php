<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class PhaseAVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_brand_containers_use_english_abdallah_exclusively(): void
    {
        $this->assertEquals('Abdallah', config('app.tutor_name'));
        foreach (['/', '/pricing', '/about', '/booking'] as $uri) {
            $response = $this->get($uri);
            $response->assertOk();
            $content = $response->getContent();
            $crawler = new Crawler($content);
            // Header brand area
            $headerText = $crawler->filter('header')->text();
            $this->assertStringContainsString('Abdallah', $headerText);
            $this->assertStringNotContainsString('Ahmad', $headerText);
            $this->assertStringNotContainsString('Ahmed', $headerText);
            $this->assertStringNotContainsString('عبدالله', $headerText);
        }
        $admin = \App\Models\Administrator::factory()->create();
        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Abdallah');
    }

    public function test_legacy_book_301_redirect_16_7(): void
    {
        $redirectResponse = $this->get('/book');
        $redirectResponse->assertStatus(301);
        $redirectResponse->assertRedirect('/booking');

        $queryResponse = $this->get('/book?utm_source=meta&utm_campaign=cairo');
        $queryResponse->assertStatus(301);
        $queryResponse->assertRedirect('/booking?utm_source=meta&utm_campaign=cairo');

        $confirmResponse = $this->get('/book/confirmation/test-token-123?src=email');
        $confirmResponse->assertStatus(301);
        $confirmResponse->assertRedirect('/booking/confirmation/test-token-123?src=email');
    }

    /** @test */
    public function test_public_footer_exposes_localized_policy_links(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee(route('privacy'))
            ->assertSee(route('terms'))
            ->assertSee('Privacy')
            ->assertSee('Terms');
    }

    public function test_admin_mobile_navigation_drawer_16_8(): void
    {
        $admin = Administrator::create([
            'name' => 'Admin User',
            'email' => 'admin@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $view = $this->view('layouts.admin', [
            'title' => 'Dashboard',
            'slot' => '<div id="test-slot-content">Test Slot Content</div>',
        ]);

        $rendered = (string) $view;

        $this->assertStringContainsString('sidebarOpen: false', $rendered);
        $this->assertStringContainsString('role="dialog"', $rendered);
        $this->assertStringContainsString('aria-modal="true"', $rendered);
        $this->assertStringContainsString('x-cloak', $rendered);
        $this->assertStringContainsString('md:hidden', $rendered);
        $this->assertStringContainsString('-translate-x-full', $rendered);
        $this->assertStringContainsString('md:translate-x-0', $rendered);
        $this->assertStringContainsString('Test Slot Content', $rendered);
    }
}
